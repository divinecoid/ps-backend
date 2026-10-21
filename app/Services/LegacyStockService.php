<?php

namespace App\Services;

use App\Models\MasterData\Color;
use App\Models\MasterData\Inventory;
use App\Models\MasterData\ProductModel;
use App\Models\MasterData\Size;
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

            if ($line['type'] === 'D') {
                $this->registerDozenPieces($line);
            }
        }

        return $product;
    }

    /**
     * A dozen (D) barcode also gets its 12 piece (P) barcodes, so each piece
     * can be scanned later when it moves to the small warehouse (Mutasi only
     * accepts P barcodes). They are registry rows only: no rack and no
     * stock of their own — the dozen barcode already added the 12 pcs.
     * Piece numbers continue across dozens: dozen n owns pieces
     * (n-1)*12+1 .. n*12.
     */
    private function registerDozenPieces(array $line): void
    {
        $parts = explode('|', $line['barcode']);
        $dozenNumber = (int) array_pop($parts);
        array_pop($parts); // the "D" group
        $now = now();

        $rows = [];
        for ($j = 1; $j <= self::DOZEN_QTY; $j++) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'model_id' => $line['model_id'],
                'rack_id' => null,
                'color_id' => $line['color_id'],
                'size_id' => $line['size_id'],
                'series' => $line['series'],
                'barcode' => implode('|', [...$parts, 'P', ($dozenNumber - 1) * self::DOZEN_QTY + $j]),
                'stock_batch_id' => $line['batch_id'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        Product::insert($rows);
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

    /** Barcode group letter (D / P) of an old-stock barcode: cmt|series|sku|color|size|GROUP|number. */
    public function group(Product $product): ?string
    {
        $parts = explode('|', (string) $product->barcode);

        return $parts[count($parts) - 2] ?? null;
    }

    /**
     * Pieces this row accounts for in the big warehouse: a dozen barcode 12,
     * a lone piece barcode 1, and 0 for the registry-only piece barcodes of
     * a dozen (their stock is already inside the dozen) or for rows that
     * already sit in a small-warehouse rack.
     */
    public function stockUnits(Product $product): int
    {
        if ($product->stock_batch_id === null || $product->rack_id !== null) {
            return 0;
        }
        if ($this->group($product) === 'D') {
            return self::DOZEN_QTY;
        }

        $belongsToDozen = Product::withTrashed()
            ->where('stock_batch_id', $product->stock_batch_id)
            ->where('series', $product->series)
            ->where('barcode', 'like', '%|D|%')
            ->exists();

        return $belongsToDozen ? 0 : 1;
    }

    /** Takes the row's stock back out of the big warehouse (delete / before an attribute change). */
    public function release(Product $product): void
    {
        $units = $this->stockUnits($product);
        if ($units === 0) {
            return;
        }

        $inventory = Inventory::where([
            'model_id' => $product->model_id,
            'color_id' => $product->color_id,
            'size_id' => $product->size_id,
        ])->first();
        $detail = $inventory?->detail()->where('series', $product->series)->first();

        if ($detail) {
            $detail->update(['quantity' => max(0, $detail->quantity - $units)]);
        }
    }

    /** Puts the row's stock (back) into the big warehouse (restore / after an attribute change). */
    public function reclaim(Product $product): void
    {
        $units = $this->stockUnits($product);
        if ($units === 0) {
            return;
        }

        $inventory = Inventory::firstOrCreate([
            'model_id' => $product->model_id,
            'color_id' => $product->color_id,
            'size_id' => $product->size_id,
        ]);
        $detail = $inventory->detail()->firstOrCreate(['series' => $product->series], ['quantity' => 0]);
        $detail->increment('quantity', $units);
    }

    /**
     * Applies new model / color / size to old-stock rows. Stock moves with
     * them and the barcodes are rewritten so their sku / color / size parts
     * match (labels printed earlier must be reprinted). `$rackId` becomes
     * the big-warehouse rack of the affected stock.
     *
     * @param \Illuminate\Support\Collection<int,Product> $products
     * @param array{model_id?:?string,color_id?:?string,size_id?:?string} $attributes
     */
    public function updateRows($products, array $attributes, ?string $rackId = null): void
    {
        $attributes = array_filter($attributes);
        $model = isset($attributes['model_id']) ? ProductModel::find($attributes['model_id']) : null;
        $color = isset($attributes['color_id']) ? Color::find($attributes['color_id']) : null;
        $size = isset($attributes['size_id']) ? Size::find($attributes['size_id']) : null;

        foreach ($products as $product) {
            if ($attributes) {
                $this->release($product);

                $changes = $attributes;
                $parts = explode('|', (string) $product->barcode);
                if (count($parts) === 7) {
                    $parts[2] = $model?->sku ?? $parts[2];
                    $parts[3] = $color?->code ?? $parts[3];
                    $parts[4] = $size?->code ?? $parts[4];
                    $changes['barcode'] = implode('|', $parts);
                }
                $product->update($changes);
            }

            if ($rackId !== null) {
                if ($product->rack_id !== null) {
                    $product->update(['rack_id' => $rackId]);
                } elseif ($this->stockUnits($product) > 0) {
                    Inventory::firstOrCreate([
                        'model_id' => $product->model_id,
                        'color_id' => $product->color_id,
                        'size_id' => $product->size_id,
                    ])->update(['rack_id' => $rackId]);
                }
            }

            if ($attributes) {
                $this->reclaim($product);
            }
        }
    }
}
