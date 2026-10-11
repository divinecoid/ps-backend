<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\Transactions\CmtPayroll;
use App\Models\Transactions\ManualOutboundDetail;
use App\Models\Transactions\OrderItem;
use Illuminate\Http\Request;

class FinanceReportController extends Controller
{
    use CrudTrait;

    /**
     * Margin per unit sold, marketplace (OrderItem.price) + manual outbound
     * (ManualOutboundDetail.sell_price) combined, joined against the frozen
     * ProductCost.total_hpp snapshot for that unit.
     */
    public function profitReport(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'model_id' => 'nullable|uuid',
            'marketplace_id' => 'nullable|uuid',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $modelId = $request->input('model_id');
        $marketplaceId = $request->input('marketplace_id');

        $rows = collect();

        $orderItems = OrderItem::with(['product.cost', 'product.model', 'product.size', 'order'])
            ->whereHas('product')
            ->when($dateFrom, fn($q) => $q->whereHas('order', fn($q2) => $q2->whereDate('created_at', '>=', $dateFrom)))
            ->when($dateTo, fn($q) => $q->whereHas('order', fn($q2) => $q2->whereDate('created_at', '<=', $dateTo)))
            ->when($modelId, fn($q) => $q->whereHas('product', fn($q2) => $q2->where('model_id', $modelId)))
            ->when($marketplaceId, fn($q) => $q->whereHas('order', fn($q2) => $q2->where('marketplace_id', $marketplaceId)))
            ->get();

        foreach ($orderItems as $item) {
            $sellPrice = (float) ($item->discounted_price ?? $item->price ?? 0);
            $hpp = (float) ($item->product?->cost?->total_hpp ?? 0);
            $isEstimated = $item->product?->cost === null || $item->product?->cost?->is_estimated;

            $rows->push([
                'channel' => 'marketplace',
                'source_id' => $item->id,
                'model' => $item->product?->model?->name,
                'size' => $item->product?->size?->name,
                'sell_price' => $sellPrice,
                'hpp' => $hpp,
                'margin_rp' => round($sellPrice - $hpp, 2),
                'margin_pct' => $sellPrice > 0 ? round((($sellPrice - $hpp) / $sellPrice) * 100, 2) : null,
                'is_estimated' => (bool) $isEstimated,
            ]);
        }

        if (!$marketplaceId) {
            $manualDetails = ManualOutboundDetail::with(['product.cost', 'model', 'manualOutbound'])
                ->when($dateFrom, fn($q) => $q->whereHas('manualOutbound', fn($q2) => $q2->whereDate('outbound_date', '>=', $dateFrom)))
                ->when($dateTo, fn($q) => $q->whereHas('manualOutbound', fn($q2) => $q2->whereDate('outbound_date', '<=', $dateTo)))
                ->when($modelId, fn($q) => $q->where('model_id', $modelId))
                ->get();

            foreach ($manualDetails as $detail) {
                $sellPrice = (float) ($detail->sell_price ?? 0);
                $hpp = (float) ($detail->product?->cost?->total_hpp ?? 0);
                $isEstimated = $detail->sell_price === null || $detail->product?->cost === null || $detail->product?->cost?->is_estimated;

                $rows->push([
                    'channel' => 'manual',
                    'source_id' => $detail->id,
                    'model' => $detail->model?->name,
                    'size' => $detail->size?->name,
                    'sell_price' => $sellPrice,
                    'hpp' => $hpp,
                    'margin_rp' => round($sellPrice - $hpp, 2),
                    'margin_pct' => $sellPrice > 0 ? round((($sellPrice - $hpp) / $sellPrice) * 100, 2) : null,
                    'is_estimated' => (bool) $isEstimated,
                ]);
            }
        }

        $totalRevenue = round($rows->sum('sell_price'), 2);
        $totalHpp = round($rows->sum('hpp'), 2);

        return $this->successResponse([
            'rows' => $rows->values(),
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_hpp' => $totalHpp,
                'total_margin' => round($totalRevenue - $totalHpp, 2),
                'estimated_count' => $rows->where('is_estimated', true)->count(),
                'total_count' => $rows->count(),
            ],
        ]);
    }

    /**
     * Total CMT fees owed, grouped by status, optionally filtered by CMT/period.
     */
    public function cmtPayrollSummary(Request $request)
    {
        $request->validate([
            'cmt_id' => 'nullable|uuid',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
        ]);

        $query = CmtPayroll::with('cmt')
            ->when($request->cmt_id, fn($q) => $q->where('cmt_id', $request->cmt_id))
            ->when($request->period_start, fn($q) => $q->whereDate('period_start', '>=', $request->period_start))
            ->when($request->period_end, fn($q) => $q->whereDate('period_end', '<=', $request->period_end));

        $payrolls = $query->get();

        return $this->successResponse([
            'by_status' => $payrolls->groupBy('status')->map(fn($group) => [
                'total_pcs' => $group->sum('total_pcs'),
                'total_amount' => round($group->sum('total_amount'), 2),
                'count' => $group->count(),
            ]),
            'outstanding' => round(
                $payrolls->whereIn('status', ['draft', 'approved'])->sum('total_amount'),
                2
            ),
        ]);
    }
}
