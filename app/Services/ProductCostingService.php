<?php

namespace App\Services;

use App\Models\FabricCuttingFabric;
use App\Models\MasterData\Product;
use App\Models\MasterData\ProductCost;
use App\Models\Transactions\RequestDetail;

class ProductCostingService
{
    /**
     * Roll up modal (HPP) for a freshly-created Product, snapshotting it so later
     * price changes upstream (fabric price, CMT fee) don't retroactively change
     * already-sold units' recorded cost.
     *
     * Fabric cost: traced RequestDetail -> FabricCutting (via cloth_id) -> the
     * FabricCuttingFabric rows for that cutting job, averaged if multiple rolls
     * were consumed for the job (simplest reasonable approach — a single cutting
     * job's output isn't sub-divided by which specific roll produced which piece).
     * That average already includes each roll's allocated shipping cost (see
     * FabricCuttingController), so ProductCost.shipping_cost is kept at 0 here to
     * avoid double-counting; it's embedded inside fabric_cost instead.
     *
     * CMT fee: RequestDetail.unit_fee (0 if not set).
     *
     * Historical data with no cost recorded anywhere yields total_hpp = 0 and
     * is_estimated = true, so profit reports can flag/exclude it rather than
     * showing a false 100% margin.
     */
    public function calculateForProduct(Product $product, RequestDetail $requestDetail): ProductCost
    {
        $snapshots = FabricCuttingFabric::where('fabric_cutting_id', $requestDetail->cloth_id)
            ->pluck('unit_cost_snapshot');

        $hasFabricCost = $snapshots->filter(fn($v) => $v !== null && $v > 0)->isNotEmpty();
        $fabricCost = $snapshots->isNotEmpty() ? round((float) $snapshots->avg(), 2) : 0;

        $cmtFee = (float) ($requestDetail->unit_fee ?? 0);
        $isEstimated = !$hasFabricCost || $requestDetail->unit_fee === null;

        $totalHpp = round($fabricCost + $cmtFee, 2);

        return ProductCost::updateOrCreate(
            ['product_id' => $product->id],
            [
                'fabric_cost' => $fabricCost,
                'shipping_cost' => 0,
                'cmt_fee' => $cmtFee,
                'total_hpp' => $totalHpp,
                'is_estimated' => $isEstimated,
                'calculated_at' => now(),
            ]
        );
    }
}
