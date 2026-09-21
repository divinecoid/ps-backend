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
            'paid_at' => $data->paid_at,
            'created_at' => $data->created_at,
            'details' => $data->details->map(fn(CmtPayrollDetail $d) => [
                'model' => $d->model?->name,
                'qty' => $d->qty,
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
     * The fee per piece is looked up from the CMT model rate master data
     * (per garment model + CMT kategori). If no rate is configured for a
     * model/kategori combination, falls back to the manual unit_fee
     * captured on the RequestDetail.
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
                        $unitFee = $modelRate !== null
                            ? (float) $modelRate
                            : (float) ($requestDetail?->unit_fee ?? 0);
                        $qty = (int) $rd->qty;
                        $amount = round($unitFee * $qty, 2);

                        $rows[] = [
                            'id' => (string) Str::uuid(),
                            'cmt_payroll_id' => $payroll->id,
                            'request_detail_id' => $requestDetail?->id,
                            'received_log_detail_id' => $rd->id,
                            'model_id' => $rd->model_id,
                            'qty' => $qty,
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
        $payroll->update(['status' => 'paid', 'paid_at' => now()]);
        return $this->successResponse(($this->structure())($payroll->fresh(['cmt', 'details.model'])));
    }

    public function destroy($id)
    {
        return $this->baseDelete(CmtPayroll::class, $id);
    }
}
