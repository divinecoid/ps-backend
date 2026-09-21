<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\CMT;
use App\Models\MasterData\CmtModelRate;
use App\Models\Transactions\CmtPayroll;
use App\Models\Transactions\CmtPayrollDetail;
use App\Models\Transactions\ReceivedlogDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CmtPayrollController extends Controller
{
    use CrudTrait;

    /**
     * Maps leftover pieces (1-11, not making a full dozen) to their paid
     * lusin-equivalent fraction, per the CMT's "HITUNGAN PCS" convention
     * (e.g. 3 loose pcs is paid as 0.25 lusin, not 3/12 = 0.25... but 5 pcs
     * is paid as 0.42, not 0.4167). Sourced from Rincian Penggajian CMT.
     */
    private const PIECE_TO_LUSIN = [
        1 => 0.08,
        2 => 0.17,
        3 => 0.25,
        4 => 0.33,
        5 => 0.42,
        6 => 0.5,
        7 => 0.58,
        8 => 0.66,
        9 => 0.75,
        10 => 0.83,
        11 => 0.93,
    ];

    /**
     * Converts a piece quantity into its paid lusin (dozen) equivalent:
     * full dozens count as 1.0 lusin each, and any leftover pieces are
     * converted via the fixed CMT piece-to-lusin table above.
     */
    private function pcsToLusin(int $pcs): float
    {
        $dozens = intdiv($pcs, 12);
        $remainder = $pcs % 12;

        return $dozens + (self::PIECE_TO_LUSIN[$remainder] ?? 0);
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
                'model' => $d->model?->name,
                'qty' => $d->qty,
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
            ['cmt', 'details.model'],
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
            ['cmt', 'details.model'],
            $this->structure()
        );
    }

    /**
     * Generate a draft payroll for a CMT over a period: sums non-rejected
     * ReceivedlogDetail rows in that window not yet attached to any payroll.
     * The rate is looked up from the CMT model rate master data (per
     * garment model + CMT kategori) and is priced **per lusin (dozen)**,
     * not per piece — pieces are converted to their lusin equivalent via
     * pcsToLusin() before multiplying by the rate. If no rate is
     * configured for a model/kategori combination, falls back to the
     * manual per-piece unit_fee captured on the RequestDetail.
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

                $alreadyPaidDetailIds = CmtPayrollDetail::whereNotNull('received_log_detail_id')
                    ->pluck('received_log_detail_id');

                $receivedDetails = ReceivedlogDetail::with(['requestDetail.request', 'model'])
                    ->where('is_rejected', false)
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
                        ->pluck('rate', 'model_id');

                    $rows = [];
                    $totalPcs = 0;
                    $totalAmount = 0;

                    foreach ($receivedDetails as $rd) {
                        $requestDetail = $rd->requestDetail;
                        $modelRate = $rateMap->get($rd->model_id);
                        $qty = (int) $rd->qty;

                        if ($modelRate !== null) {
                            $qtyLusin = $this->pcsToLusin($qty);
                            $unitFee = (float) $modelRate;
                            $amount = round($unitFee * $qtyLusin, 2);
                        } else {
                            // Fallback: legacy manual per-piece fee (no master rate configured yet).
                            $qtyLusin = null;
                            $unitFee = (float) ($requestDetail?->unit_fee ?? 0);
                            $amount = round($unitFee * $qty, 2);
                        }

                        $rows[] = [
                            'id' => (string) Str::uuid(),
                            'cmt_payroll_id' => $payroll->id,
                            'request_detail_id' => $requestDetail?->id,
                            'received_log_detail_id' => $rd->id,
                            'model_id' => $rd->model_id,
                            'qty' => $qty,
                            'qty_lusin' => $qtyLusin,
                            'unit_fee' => $unitFee,
                            'amount' => $amount,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        $totalPcs += $qty;
                        $totalAmount += $amount;
                    }

                    CmtPayrollDetail::insert($rows);

                    $payroll->update([
                        'total_pcs' => $totalPcs,
                        'total_amount' => $totalAmount,
                    ]);

                    return $this->successResponse(
                        ($this->structure())($payroll->fresh(['cmt', 'details.model']))
                    );
                });
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
        return $this->successResponse(($this->structure())($payroll->fresh(['cmt', 'details.model'])));
    }

    public function markPaid($id)
    {
        $payroll = CmtPayroll::findOrFail($id);
        if ($payroll->status !== 'approved') {
            return $this->errorResponse(422, 'Hanya payroll berstatus approved yang bisa ditandai lunas.');
        }
        $payroll->update(['status' => 'paid', 'is_paid' => true, 'paid_at' => now()]);
        return $this->successResponse(($this->structure())($payroll->fresh(['cmt', 'details.model'])));
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
        return $this->successResponse(($this->structure())($payroll->fresh(['cmt', 'details.model'])));
    }

    public function destroy($id)
    {
        return $this->baseDelete(CmtPayroll::class, $id);
    }
}
