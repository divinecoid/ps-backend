<?php

namespace App\Services;

use App\Models\MasterData\Inventory;
use App\Models\MasterData\Product;
use App\Models\MasterData\Rack;
use Illuminate\Support\Str;

/**
 * "Input Stok Lama": barcodes for stock that already exists but never went
 * through a CMT request. Such stock goes straight into the BIG warehouse
 * (Inventory/InventoryDetail) without having to be scanned in first — a D
 * (dozen) barcode adds 12 pcs, a P (piece) barcode adds 1 pc — and is
 * deducted later when a P barcode is moved to the small warehouse through
 * Mutasi (see MutationController).
 *
 * The Product row is kept as the barcode registry (batch listing, "already
 * used" check) but carries NO rack while the stock sits in the big
 * warehouse: small-warehouse stock is counted from Products that have a
 * rack, so giving it one now would count the same stock twice.
 */
class LegacyStockService
{
    public const DOZEN_QTY = 12;

    /**
     * Registers one old-stock barcode.
     *
     * @param array{model_id:string,color_id:string,size_id:string,rack_id:?string,
     *              series:string,barcode:string,type:string,batch_id:string} $line
     */
    public function register(array $line): Product
    {
        $rack = $line['rack_id'] ? Rack::with('warehouse')->find($line['rack_id']) : null;
        // Only "no rack" or a rack in a BESAR warehouse goes to the big
        // warehouse. A rack in a KECIL warehouse — or in a warehouse with no
        // type set — keeps the old behaviour: the product is placed straight
        // into that rack (small-warehouse stock).
        $toBig = !$rack || $rack->warehouse?->type === 'BESAR';

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'model_id' => $line['model_id'],
            'rack_id' => $toBig ? null : $rack->id,
            'color_id' => $line['color_id'],
            'size_id' => $line['size_id'],
            'series' => $line['series'],
            'barcode' => $line['barcode'],
            'stock_batch_id' => $line['batch_id'],
        ]);

        if ($toBig) {
            $this->addToBigWarehouse($line, $rack);
        }

        return $product;
    }

    private function addToBigWarehouse(array $line, ?Rack $rack): void
    {
        $inventory = Inventory::firstOrCreate([
            'model_id' => $line['model_id'],
            'color_id' => $line['color_id'],
            'size_id' => $line['size_id'],
        ]);

        if ($inventory->rack_id === null) {
            $rackId = $rack?->id ?? $this->defaultBigRackId($line['model_id'], $line['color_id']);
            if ($rackId) {
                $inventory->update(['rack_id' => $rackId]);
            }
        }

        $detail = $inventory->detail()->firstOrCreate(
            ['series' => $line['series']],
            ['quantity' => 0]
        );
        $detail->increment('quantity', $line['type'] === 'D' ? self::DOZEN_QTY : 1);
    }

    /** Same fallback order the CMT receive flow uses to pick a big-warehouse rack. */
    private function defaultBigRackId(string $modelId, string $colorId): ?string
    {
        $big = fn() => Rack::whereHas('warehouse', fn($q) => $q->where('type', 'BESAR'));

        return ($big()->where('model_id', $modelId)->where('color_id', $colorId)->first()
            ?? $big()->where('model_id', $modelId)->whereNull('color_id')->first()
            ?? $big()->whereNull('model_id')->whereNull('color_id')->first())?->id;
    }

    /**
     * Takes one piece out of the big warehouse when an old-stock barcode is
     * moved to the small warehouse. Prefers the barcode's own series; if
     * that is already empty (e.g. the piece came out of a dozen barcode's
     * 12) it takes from the oldest series that still has stock.
     */
    public function deductOnePiece(Product $product): void
    {
        $inventory = Inventory::where([
            'model_id' => $product->model_id,
            'color_id' => $product->color_id,
            'size_id' => $product->size_id,
        ])->first();

        if (!$inventory) {
            return;
        }

        $detail = $inventory->detail()->where('series', $product->series)->where('quantity', '>', 0)->first()
            ?? $inventory->detail()->where('quantity', '>', 0)->orderBy('series')->first();

        $detail?->decrement('quantity');
    }
}
