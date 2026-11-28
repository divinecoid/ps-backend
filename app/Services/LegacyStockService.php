<?php

namespace App\Services;

use App\Models\MasterData\Color;
use App\Models\MasterData\Inventory;
use App\Models\MasterData\InventoryDetail;
use App\Models\MasterData\ProductModel;
use App\Models\MasterData\Size;
use App\Models\MasterData\Product;
use App\Models\MasterData\Rack;
use Illuminate\Support\Str;

/**
 * "Input Stok Lama": barcodes for stock that already exists but never went
 * through a CMT request. Such stock always goes straight into the BIG
 * warehouse (Inventory/InventoryDetail) without having to be scanned in
 * first — a D (dozen) barcode adds 12 pcs, a P (piece) barcode adds 1 pc —
 * and is deducted later when a P barcode is moved to the small warehouse
 * through Mutasi (see MutationController).
 *
 * The Product row is kept as the barcode registry (batch listing, "already
 * used" check) but carries NO rack while the stock sits in the big
 * warehouse: small-warehouse stock is counted from Products that have a
 * rack, so giving it one now would count the same stock twice.
 *
 * A batch can hold thousands of rows, so everything here works in bulk: rows
 * are read once, stock changes are summed per series and written once.
 *
 * This service never renders labels; barcode PDFs are a separate background
 * step (see GenerateBarcodePdfJob).
 */
class LegacyStockService
{
    public const DOZEN_QTY = 12;
    private const CHUNK = 500;

    /**
     * Registers one old-stock barcode.
     *
     * @param array{model_id:string,color_id:string,size_id:string,rack_id:?string,
     *              series:string,barcode:string,type:string,batch_id:string} $line
     */
    public function register(array $line): void
    {
        $this->registerMany([$line]);
    }

    /**
     * Registers old-stock barcodes into the big warehouse. `rack_id` is
     * optional and, when given, must be a big-warehouse rack.
     *
     * @param array<int,array{model_id:string,color_id:string,size_id:string,rack_id:?string,
     *              series:string,barcode:string,type:string,batch_id:string}> $lines
     * @throws \InvalidArgumentException when a rack is not in the big warehouse
     */
    public function registerMany(array $lines): void
    {
        $racks = Rack::with('warehouse')
            ->whereIn('id', collect($lines)->pluck('rack_id')->filter()->unique()->values())
            ->get()
            ->keyBy('id');
        foreach ($racks as $rack) {
            if ($rack->warehouse?->type !== 'BESAR') {
                throw new \InvalidArgumentException("Rak {$rack->name} bukan rak gudang besar. Stok lama hanya bisa masuk ke gudang besar.");
            }
        }

        $now = now();
        $rows = [];
        $moves = [];
        $preferredRacks = [];
        foreach ($lines as $line) {
            $rows[] = $this->productRow($line, $line['barcode'], $now);
            $units = 1;
            if ($line['type'] === 'D') {
                $units = self::DOZEN_QTY;
                array_push($rows, ...$this->dozenPieceRows($line, [], $now));
            }
            $key = $this->stockKey($line['model_id'], $line['color_id'], $line['size_id'], $line['series']);
            $moves[$key] = ($moves[$key] ?? 0) + $units;
            if ($line['rack_id']) {
                $preferredRacks[$this->comboKey($line['model_id'], $line['color_id'], $line['size_id'])] ??= $line['rack_id'];
            }
        }

        $this->insertRows($rows);
        $this->addStock($moves, $preferredRacks);
    }

    private function productRow(array $line, string $barcode, $now): array
    {
        return [
            'id' => (string) Str::uuid(),
            'model_id' => $line['model_id'],
            'rack_id' => null,
            'color_id' => $line['color_id'],
            'size_id' => $line['size_id'],
            'series' => $line['series'],
            'barcode' => $barcode,
            'stock_batch_id' => $line['batch_id'],
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * A dozen (D) barcode also gets its 12 piece (P) barcodes, so each piece
     * can be scanned later when it moves to the small warehouse (Mutasi only
     * accepts P barcodes). They are registry rows only: no rack and no
     * stock of their own — the dozen barcode already added the 12 pcs.
     */
    private function dozenPieceRows(array $line, array $existingBarcodes, $now): array
    {
        $rows = [];
        foreach ($this->pieceBarcodesOf($line['barcode']) as $barcode) {
            if (!isset($existingBarcodes[$barcode])) {
                $rows[] = $this->productRow($line, $barcode, $now);
            }
        }

        return $rows;
    }

    /**
     * The 12 piece barcodes owned by a dozen barcode. Piece numbers continue
     * across dozens: dozen n owns pieces (n-1)*12+1 .. n*12.
     *
     * @return string[]
     */
    public function pieceBarcodesOf(string $dozenBarcode): array
    {
        $parts = explode('|', $dozenBarcode);
        $dozenNumber = (int) array_pop($parts);
        array_pop($parts); // the "D" group
        $barcodes = [];
        for ($j = 1; $j <= self::DOZEN_QTY; $j++) {
            $barcodes[] = implode('|', [...$parts, 'P', ($dozenNumber - 1) * self::DOZEN_QTY + $j]);
        }

        return $barcodes;
    }

    /** The dozen barcode a piece barcode would belong to. */
    private function dozenBarcodeOf(string $pieceBarcode): ?string
    {
        $parts = explode('|', $pieceBarcode);
        if (count($parts) < 3) {
            return null;
        }
        $number = (int) array_pop($parts);
        if (array_pop($parts) !== 'P' || $number < 1) {
            return null;
        }

        return implode('|', [...$parts, 'D', intdiv($number - 1, self::DOZEN_QTY) + 1]);
    }

    /**
     * Gives every dozen (D) barcode of a big-warehouse batch its 12 piece
     * barcodes. Batches entered before pieces were registered per dozen only
     * hold the D rows; without this, printing "Satuan" is short and growing
     * the batch leaves the older dozens without pieces. Idempotent.
     */
    public function backfillDozenPieces(string $batchId): int
    {
        $products = Product::where('stock_batch_id', $batchId)->get();
        $existing = $products->pluck('barcode')->flip()->all();
        $now = now();
        $rows = [];
        foreach ($products as $dozen) {
            if ($dozen->rack_id !== null || $this->group($dozen) !== 'D') {
                continue;
            }
            array_push($rows, ...$this->dozenPieceRows([
                'model_id' => $dozen->model_id,
                'color_id' => $dozen->color_id,
                'size_id' => $dozen->size_id,
                'series' => $dozen->series,
                'barcode' => $dozen->barcode,
                'batch_id' => $batchId,
            ], $existing, $now));
        }
        $this->insertRows($rows);

        return count($rows);
    }

    private function insertRows(array $rows): void
    {
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            Product::insert($chunk);
        }
    }

    private function comboKey(string $modelId, string $colorId, string $sizeId): string
    {
        return implode('|', [$modelId, $colorId, $sizeId]);
    }

    private function stockKey(string $modelId, string $colorId, string $sizeId, string $series): string
    {
        // Series never contains "|" (validated on input).
        return implode('|', [$modelId, $colorId, $sizeId, $series]);
    }

    /** @return array<string,array<string,int>> combo key => [series => units] */
    private function byCombo(array $moves): array
    {
        $grouped = [];
        foreach ($moves as $key => $units) {
            [$modelId, $colorId, $sizeId, $series] = explode('|', $key, 4);
            $grouped[$this->comboKey($modelId, $colorId, $sizeId)][$series] = $units;
        }

        return $grouped;
    }

    /**
     * Puts stock into the big warehouse.
     *
     * @param array<string,int> $moves stock key => pieces
     * @param array<string,string> $preferredRacks combo key => rack for a combo that has none yet
     */
    private function addStock(array $moves, array $preferredRacks = []): void
    {
        foreach ($this->byCombo(array_filter($moves)) as $combo => $series) {
            [$modelId, $colorId, $sizeId] = explode('|', $combo);
            $inventory = Inventory::firstOrCreate([
                'model_id' => $modelId,
                'color_id' => $colorId,
                'size_id' => $sizeId,
            ]);

            if ($inventory->rack_id === null) {
                $rackId = $preferredRacks[$combo] ?? $this->defaultBigRackId($modelId, $colorId);
                if ($rackId) {
                    $inventory->update(['rack_id' => $rackId]);
                }
            }

            $details = $inventory->detail()->whereIn('series', array_map('strval', array_keys($series)))->get()->keyBy('series');
            foreach ($series as $name => $units) {
                if ($detail = $details->get($name)) {
                    $detail->increment('quantity', $units);
                } else {
                    $inventory->detail()->create(['series' => (string) $name, 'quantity' => $units]);
                }
            }
        }
    }

    /**
     * Takes stock out of the big warehouse, never going below zero.
     *
     * @param array<string,int> $moves stock key => pieces
     * @return array<string,int> stock key => pieces actually taken
     */
    private function removeStock(array $moves): array
    {
        $taken = [];
        foreach ($this->byCombo(array_filter($moves)) as $combo => $series) {
            [$modelId, $colorId, $sizeId] = explode('|', $combo);
            // A combo can have more than one inventory row (see InventoryController).
            $inventoryIds = Inventory::where(['model_id' => $modelId, 'color_id' => $colorId, 'size_id' => $sizeId])->pluck('id');
            $details = InventoryDetail::whereIn('inventory_id', $inventoryIds)
                ->whereIn('series', array_map('strval', array_keys($series)))
                ->orderBy('created_at')
                ->get()
                ->groupBy('series');

            foreach ($series as $name => $units) {
                $left = $units;
                foreach ($details->get($name, []) as $detail) {
                    $take = min($left, max(0, (int) $detail->quantity));
                    if ($take > 0) {
                        $detail->decrement('quantity', $take);
                        $left -= $take;
                    }
                }
                $taken["{$combo}|{$name}"] = $units - $left;
            }
        }

        return $taken;
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
     * Big-warehouse pieces each row accounts for: a dozen barcode 12, a lone
     * piece barcode 1, and 0 for the registry-only piece barcodes of a dozen
     * (their stock is already inside the dozen) or for rows that sit in a
     * small-warehouse rack.
     *
     * @param \Illuminate\Support\Collection<int,Product> $products
     * @return array<string,int> product id => pieces
     */
    public function stockUnits($products): array
    {
        $batchIds = $products->pluck('stock_batch_id')->filter()->unique()->values();
        $dozens = Product::withTrashed()
            ->whereIn('stock_batch_id', $batchIds)
            ->where('barcode', 'like', '%|D|%')
            ->get(['stock_batch_id', 'barcode'])
            ->mapWithKeys(fn($d) => ["{$d->stock_batch_id}#{$d->barcode}" => true])
            ->all();

        $units = [];
        foreach ($products as $product) {
            if ($product->stock_batch_id === null || $product->rack_id !== null) {
                $units[$product->id] = 0;
            } elseif ($this->group($product) === 'D') {
                $units[$product->id] = self::DOZEN_QTY;
            } else {
                $dozen = $this->dozenBarcodeOf((string) $product->barcode);
                $units[$product->id] = $dozen !== null && isset($dozens["{$product->stock_batch_id}#{$dozen}"]) ? 0 : 1;
            }
        }

        return $units;
    }

    /** @return array<string,int> stock key => pieces held by these rows */
    private function stockOf($products, array $units): array
    {
        $moves = [];
        foreach ($products as $product) {
            if ($count = $units[$product->id] ?? 0) {
                $key = $this->stockKey($product->model_id, $product->color_id, $product->size_id, (string) $product->series);
                $moves[$key] = ($moves[$key] ?? 0) + $count;
            }
        }

        return $moves;
    }

    /** Takes the rows' stock back out of the big warehouse (delete). */
    public function release($products): void
    {
        $this->removeStock($this->stockOf($products, $this->stockUnits($products)));
    }

    /** Puts the rows' stock back into the big warehouse (restore). */
    public function reclaim($products): void
    {
        $this->addStock($this->stockOf($products, $this->stockUnits($products)));
    }

    /**
     * The given rows plus, for each dozen (D) barcode among them, its own 12
     * piece barcodes. Matched by barcode, not by series: a series typed by
     * the user is shared by every dozen of a batch.
     */
    public function withDozenPieces($products, bool $trashed = false)
    {
        $extra = collect();
        $dozens = $products->filter(fn($p) => $p->stock_batch_id !== null && $this->group($p) === 'D');
        foreach ($dozens->groupBy('stock_batch_id') as $batchId => $rows) {
            $barcodes = $rows->flatMap(fn($d) => $this->pieceBarcodesOf($d->barcode))->values();
            foreach ($barcodes->chunk(self::CHUNK) as $chunk) {
                $extra = $extra->concat(Product::query()
                    ->when($trashed, fn($q) => $q->onlyTrashed())
                    ->where('stock_batch_id', $batchId)
                    ->whereIn('barcode', $chunk->all())
                    ->get());
            }
        }

        return $products->concat($extra)->unique('id')->values();
    }

    /** One row's whole dozen (the D barcode and its pieces), or just the row if it is a lone piece. */
    public function dozenFamily(Product $product)
    {
        if ($this->group($product) === 'P') {
            $dozenBarcode = $this->dozenBarcodeOf((string) $product->barcode);
            $dozen = $dozenBarcode === null ? null : Product::where('stock_batch_id', $product->stock_batch_id)
                ->where('barcode', $dozenBarcode)
                ->first();
            $product = $dozen ?? $product;
        }

        return $this->withDozenPieces(collect([$product]));
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
        $units = $this->stockUnits($products);

        if ($attributes) {
            $model = isset($attributes['model_id']) ? ProductModel::find($attributes['model_id']) : null;
            $color = isset($attributes['color_id']) ? Color::find($attributes['color_id']) : null;
            $size = isset($attributes['size_id']) ? Size::find($attributes['size_id']) : null;

            // Only what is really on the shelf moves to the new item, so an
            // edit can neither create stock nor lose it.
            $taken = $this->removeStock($this->stockOf($products, $units));
            $incoming = [];
            foreach ($taken as $key => $count) {
                [$modelId, $colorId, $sizeId, $series] = explode('|', $key, 4);
                $target = $this->stockKey(
                    $attributes['model_id'] ?? $modelId,
                    $attributes['color_id'] ?? $colorId,
                    $attributes['size_id'] ?? $sizeId,
                    $series
                );
                $incoming[$target] = ($incoming[$target] ?? 0) + $count;
            }

            $now = now();
            $rows = [];
            foreach ($products as $product) {
                $parts = explode('|', (string) $product->barcode);
                if (count($parts) === 7) {
                    $parts[2] = $model?->sku ?? $parts[2];
                    $parts[3] = $color?->code ?? $parts[3];
                    $parts[4] = $size?->code ?? $parts[4];
                    $product->barcode = implode('|', $parts);
                }
                $product->forceFill($attributes);
                $product->updated_at = $now;
                $rows[] = $product->getAttributes();
                $product->syncOriginal();
            }
            foreach (array_chunk($rows, self::CHUNK) as $chunk) {
                Product::upsert($chunk, ['id'], ['model_id', 'color_id', 'size_id', 'barcode', 'updated_at']);
            }
            $this->addStock($incoming);
        }

        if ($rackId !== null) {
            $racked = $products->whereNotNull('rack_id');
            if ($racked->isNotEmpty()) {
                Product::whereIn('id', $racked->pluck('id'))->update(['rack_id' => $rackId]);
            }
            $products
                ->filter(fn($p) => ($units[$p->id] ?? 0) > 0)
                ->unique(fn($p) => $this->comboKey($p->model_id, $p->color_id, $p->size_id))
                ->each(fn($p) => Inventory::firstOrCreate([
                    'model_id' => $p->model_id,
                    'color_id' => $p->color_id,
                    'size_id' => $p->size_id,
                ])->update(['rack_id' => $rackId]));
        }
    }

    /**
     * Sets how many barcodes an old-stock batch holds: dozens for a dozen
     * batch, pieces otherwise. Growing registers new barcodes (numbering
     * continues, stock goes into the big warehouse like the original
     * input); shrinking deletes the highest-numbered ones and takes their
     * stock back out.
     *
     * Only a batch of a single model / color / size and a single barcode
     * group that still sits entirely in the big warehouse can be resized.
     *
     * @throws \InvalidArgumentException when the batch can't be resized
     */
    public function resizeBatch(string $batchId, int $qty): void
    {
        $rows = Product::where('stock_batch_id', $batchId)->get();
        if ($rows->isEmpty()) {
            throw new \InvalidArgumentException('Batch tidak ditemukan.');
        }
        if ($rows->contains(fn($p) => $p->rack_id !== null)) {
            throw new \InvalidArgumentException('Jumlah tidak bisa diubah: batch ini sudah ada yang berada di rak gudang kecil (sudah dimutasi).');
        }
        $combos = $rows->map(fn($p) => "{$p->model_id}|{$p->color_id}|{$p->size_id}")->unique();
        if ($combos->count() > 1) {
            throw new \InvalidArgumentException('Jumlah tidak bisa diubah: batch ini berisi lebih dari satu model/warna/ukuran.');
        }
        $groups = $rows->map(fn($p) => $this->group($p))->unique();
        $isDozen = $groups->contains('D');
        if ($isDozen && $groups->contains(fn($g) => $g !== 'D' && $g !== 'P')) {
            throw new \InvalidArgumentException('Jumlah tidak bisa diubah: format barcode batch tidak dikenali.');
        }
        if (!$isDozen && $groups->count() > 1) {
            throw new \InvalidArgumentException('Jumlah tidak bisa diubah: format barcode batch tidak dikenali.');
        }

        $type = $isDozen ? 'D' : 'P';
        $main = $rows->filter(fn($p) => $this->group($p) === $type)
            ->sortBy(fn($p) => $this->number($p->barcode))
            ->values();
        $current = $main->count();
        if ($qty === $current) {
            return;
        }

        if ($isDozen) {
            $this->backfillDozenPieces($batchId);
        }

        if ($qty > $current) {
            $this->growBatch($main, $qty - $current, $type);

            return;
        }

        $victims = $this->withDozenPieces($main->slice($qty)->values());
        $this->release($victims);
        foreach ($victims->pluck('id')->chunk(self::CHUNK) as $ids) {
            Product::whereIn('id', $ids->values()->all())->delete();
        }
    }

    private function number(string $barcode): int
    {
        return (int) substr((string) strrchr($barcode, '|'), 1);
    }

    /**
     * Registers `$count` more barcodes after the batch's highest-numbered one.
     * A series typed by the user stays the same; automatic (timestamp) series
     * get a new one per barcode.
     */
    private function growBatch($main, int $count, string $type): void
    {
        $last = $main->last();
        $parts = explode('|', (string) $last->barcode);
        if (count($parts) !== 7) {
            throw new \InvalidArgumentException('Jumlah tidak bisa diubah: format barcode batch tidak dikenali.');
        }

        $series = $main->pluck('series')->unique();
        $typed = $series->count() === 1 && !preg_match('/^\d{14}$/', (string) $series->first())
            ? (string) $series->first()
            : null;
        $number = $this->number($last->barcode);
        if ($typed !== null) {
            // Don't reissue numbers of barcodes removed from this batch earlier.
            $prefix = implode('|', array_slice($parts, 0, 6)) . '|';
            Product::withTrashed()
                ->where('stock_batch_id', $last->stock_batch_id)
                ->where('series', $typed)
                ->pluck('barcode')
                ->each(function ($barcode) use ($prefix, &$number) {
                    if (str_starts_with($barcode, $prefix)) {
                        $number = max($number, $this->number($barcode));
                    }
                });
        }

        $used = Product::withTrashed()->where('stock_batch_id', $last->stock_batch_id)->pluck('series')->flip()->all();
        $base = now();
        $offset = 0;
        $lines = [];
        for ($i = 1; $i <= $count; $i++) {
            $name = $typed;
            if ($name === null) {
                do {
                    $name = $base->copy()->addSeconds($offset++)->format('YmdHis');
                } while (isset($used[$name]));
            }

            $line = $parts;
            $line[1] = $name;
            $line[5] = $type;
            $line[6] = $number + $i;

            $lines[] = [
                'model_id' => $last->model_id,
                'color_id' => $last->color_id,
                'size_id' => $last->size_id,
                'rack_id' => null,
                'series' => $name,
                'barcode' => implode('|', $line),
                'type' => $type,
                'batch_id' => $last->stock_batch_id,
            ];
        }
        $this->registerMany($lines);
    }
}
