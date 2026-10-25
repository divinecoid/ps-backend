<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\CMT;
use App\Models\MasterData\CmtModelRate;
use App\Models\Transactions\CmtPayroll;
use App\Models\Transactions\CmtPayrollDetail;
use App\Models\Transactions\ReceivedlogDetail;
use App\Services\CmtPayrollCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CmtPayrollController extends Controller
{
    use CrudTrait;

    /** Relations needed to render a payroll via structure(). */
    private const RELATIONS = ['cmt', 'details.model', 'details.requestDetail.request', 'details.receivedItems.requestDetail.request'];

    private function pcsToLusin(int $pcs): float
    {
        return CmtPayrollCalculator::pcsToLusin($pcs);
    }

    /**
     * Seri of a payroll row, comma-joined: a grouped row spans every seri its
     * scans came from; legacy rows fall back to their own request detail.
     */
    private function serialNumbers(CmtPayrollDetail $d): ?string
    {
        $serials = $d->receivedItems
            ->map(fn($i) => $i->requestDetail?->request?->serial_number)
            ->push($d->requestDetail?->request?->serial_number)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return $serials->isEmpty() ? null : $serials->implode(', ');
    }

    private function structure()
    {
        return fn(CmtPayroll $data) => [
            'id' => $data->id,
            'cmt_id' => $data->cmt_id,
            'cmt' => $data->cmt ? [
                'code' => $data->cmt->code,
                'name' => $data->cmt->name,
            ] : null,
            'period_start' => $data->period_start,
            'period_end' => $data->period_end,
            'total_pcs' => $data->total_pcs,
            'total_amount' => $data->total_amount,
            'status' => $data->status,
            'is_paid' => $data->is_paid,
            'paid_at' => $data->paid_at,
            'created_at' => $data->created_at,
            'details' => $data->details->map(fn(CmtPayrollDetail $d) => [
                'id' => $d->id,
                'serial_number' => $this->serialNumbers($d),
                'model' => $d->model?->name,
                'qty' => $d->qty,
                'qty_dozen' => CmtPayrollCalculator::splitDozen((int) $d->qty)[0],
                'qty_piece' => CmtPayrollCalculator::splitDozen((int) $d->qty)[1],
                'qty_lusin' => $d->qty_lusin,
                'unit_fee' => $d->unit_fee,
                'amount' => $d->amount,
            ])->values(),
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            CmtPayroll::class,
            self::RELATIONS,
            ['cmt.code', 'cmt.name', 'status'],
            $this->structure(),
            null,
            ['created_at', 'desc']
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            CmtPayroll::class,
            $id,
            self::RELATIONS,
            $this->structure()
        );
    }

    /**
     * Generate a draft payroll for a CMT over a period: sums non-rejected
     * ReceivedlogDetail rows in that window not yet attached to any payroll.
     * The rate is looked up from the CMT model rate master data — keyed by
     * the garment model's CMT rate GROUP (e.g. "Pendek"/"Panjang"/"Saku",
     * since several models share the same rate) + CMT kategori — and is
     * priced **per lusin (dozen)**, not per piece; pieces are converted to
     * their lusin equivalent via pcsToLusin() before multiplying by the
     * rate. If the model has no rate group, or no rate is configured for
     * that group/kategori combination, falls back to the manual per-piece
     * unit_fee captured on the RequestDetail.
     */
    public function generate(Request $request)
    {
        return $this->baseValidate(
            $request,
            [
                'cmt_id' => 'required|uuid|exists:mdx_cmts,id',
                'period_start' => 'required|date',
                'period_end' => 'required|date|after_or_equal:period_start',
            ],
            function ($data) {
                $cmt = CMT::findOrFail($data['cmt_id']);

                // Scans already paid: legacy one-row-per-scan details store the id
                // on the detail itself; grouped details record each scan in the
                // detail_items table.
                $alreadyPaidDetailIds = CmtPayrollDetail::whereNotNull('received_log_detail_id')
                    ->pluck('received_log_detail_id')
                    ->merge(DB::table('trx_cmt_payroll_detail_items')->pluck('received_log_detail_id'));

                $receivedDetails = ReceivedlogDetail::with(['requestDetail.request', 'model.cmtRateGroup'])
                    ->where('is_rejected', false)
                    // Goods returned unsewn (returned_good / returned_defect) are not paid.
                    ->whereIn('category', ['sewn', 'extra'])
                    // Mutasi (warehouse moves) log one qty-1 row per piece against the
                    // request they came from; the goods were already paid when received.
                    // MutationController marks its logs with a "[MUTATION]" note prefix.
                    ->whereHas('receivedlog', fn($q) => $q->where(
                        fn($n) => $n->whereNull('notes')->orWhere('notes', 'not like', '[MUTATION]%')
                    ))
                    ->whereHas('requestDetail.request', fn($q) => $q->where('cmt_id', $cmt->id))
                    ->whereBetween('created_at', [
                        $data['period_start'] . ' 00:00:00',
                        $data['period_end'] . ' 23:59:59',
                    ])
                    ->whereNotIn('id', $alreadyPaidDetailIds)
                    ->get();

                if ($receivedDetails->isEmpty()) {
                    return $this->errorResponse(422, 'Tidak ada barang diterima dari CMT ini pada periode tersebut yang belum masuk penggajian.');
                }

                return DB::transaction(function () use ($data, $cmt, $receivedDetails) {
                    $payroll = CmtPayroll::create([
                        'cmt_id' => $cmt->id,
                        'period_start' => $data['period_start'],
                        'period_end' => $data['period_end'],
                        'total_pcs' => 0,
                        'total_amount' => 0,
                        'status' => 'draft',
                    ]);

                    $rateMap = CmtModelRate::where('kategori', $cmt->kategori)
                        ->pluck('rate', 'group_id');

                    // One payroll row per model (not per scanned barcode):
                    // quantities are summed first, THEN converted to lusin.
                    $groups = CmtPayrollCalculator::group(
                        $receivedDetails->map(function ($rd) use ($rateMap) {
                            $groupId = $rd->model?->cmt_rate_group_id;

                            return [
                                'id' => $rd->id,
                                'request_detail_id' => $rd->request_detail_id,
                                'model_id' => $rd->model_id,
                                'qty' => (int) $rd->qty,
                                'rate' => $groupId ? $rateMap->get($groupId) : null,
                                // Fallback: legacy manual per-piece fee (no master rate configured yet).
                                'fallback_unit_fee' => (float) ($rd->requestDetail?->unit_fee ?? 0),
                            ];
                        })
                    );

                    $totalPcs = 0;
                    $totalAmount = 0;
                    $now = now();

                    foreach ($groups as $g) {
                        $detail = CmtPayrollDetail::create([
                            'cmt_payroll_id' => $payroll->id,
                            'request_detail_id' => $g['request_detail_id'],
                            'model_id' => $g['model_id'],
                            'qty' => $g['qty'],
                            'qty_lusin' => $g['qty_lusin'],
                            'unit_fee' => $g['unit_fee'],
                            'amount' => $g['amount'],
                        ]);

                        DB::table('trx_cmt_payroll_detail_items')->insert(
                            array_map(fn($rdId) => [
                                'id' => (string) Str::uuid(),
                                'cmt_payroll_detail_id' => $detail->id,
                                'received_log_detail_id' => $rdId,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ], $g['received_log_detail_ids'])
                        );

                        $totalPcs += $g['qty'];
                        $totalAmount += $g['amount'];
                    }

                    $payroll->update([
                        'total_pcs' => $totalPcs,
                        'total_amount' => $totalAmount,
                    ]);

                    return $this->successResponse(
                        ($this->structure())($payroll->fresh(self::RELATIONS))
                    );
                });
            }
        );
    }

    /**
     * Edits a single detail row's qty/unit_fee while the payroll is still
     * draft. qty_lusin/amount are recalculated from the new qty (lusin
     * conversion only applies when the row already had a lusin rate — a
     * legacy per-piece row stays per-piece). The payroll's totals are
     * recomputed afterwards.
     */
    public function updateDetail(Request $request, $id, $detailId)
    {
        return $this->baseValidate(
            $request,
            [
                'qty' => 'required|integer|min:1',
                'unit_fee' => 'required|numeric|min:0',
            ],
            function ($data) use ($id, $detailId) {
                $payroll = CmtPayroll::findOrFail($id);
                if ($payroll->status !== 'draft') {
                    return $this->errorResponse(422, 'Hanya payroll berstatus draft yang bisa diedit.');
                }

                $detail = CmtPayrollDetail::where('cmt_payroll_id', $payroll->id)->findOrFail($detailId);

                $qty = (int) $data['qty'];
                $unitFee = (float) $data['unit_fee'];
                $qtyLusin = $detail->qty_lusin !== null ? $this->pcsToLusin($qty) : null;
                $amount = $qtyLusin !== null ? round($unitFee * $qtyLusin, 2) : round($unitFee * $qty, 2);

                $detail->update([
                    'qty' => $qty,
                    'qty_lusin' => $qtyLusin,
                    'unit_fee' => $unitFee,
                    'amount' => $amount,
                ]);

                $payroll->update([
                    'total_pcs' => $payroll->details()->sum('qty'),
                    'total_amount' => $payroll->details()->sum('amount'),
                ]);

                return $this->successResponse(($this->structure())($payroll->fresh(self::RELATIONS)));
            }
        );
    }

    public function approve($id)
    {
        $payroll = CmtPayroll::findOrFail($id);
        if ($payroll->status !== 'draft') {
            return $this->errorResponse(422, 'Hanya payroll berstatus draft yang bisa disetujui.');
        }
        $payroll->update(['status' => 'approved']);
        return $this->successResponse(($this->structure())($payroll->fresh(self::RELATIONS)));
    }

    public function markPaid($id)
    {
        $payroll = CmtPayroll::findOrFail($id);
        if ($payroll->status !== 'approved') {
            return $this->errorResponse(422, 'Hanya payroll berstatus approved yang bisa ditandai lunas.');
        }
        $payroll->update(['status' => 'paid', 'is_paid' => true, 'paid_at' => now()]);
        return $this->successResponse(($this->structure())($payroll->fresh(self::RELATIONS)));
    }

    /**
     * Reverts a payroll marked paid back to unpaid (e.g. it was flagged by
     * mistake) — drops back to 'approved' status, clears paid_at.
     */
    public function markUnpaid($id)
    {
        $payroll = CmtPayroll::findOrFail($id);
        if (!$payroll->is_paid) {
            return $this->errorResponse(422, 'Payroll ini belum ditandai lunas.');
        }
        $payroll->update(['status' => 'approved', 'is_paid' => false, 'paid_at' => null]);
        return $this->successResponse(($this->structure())($payroll->fresh(self::RELATIONS)));
    }

    public function destroy($id)
    {
        return $this->baseDelete(CmtPayroll::class, $id);
    }
}
