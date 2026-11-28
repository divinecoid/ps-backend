<?php

namespace App\Services;

use App\Models\MasterData\Color;
use App\Models\MasterData\Inventory;
use App\Models\MasterData\Product;
use App\Models\MasterData\ProductModel;
use App\Models\MasterData\Rack;
use App\Models\MasterData\Size;

/**
 * Counting one rack. "System" stock is what Cek Stok shows for the rack:
 * piece barcodes placed on it (small warehouse) plus big-warehouse inventory
 * assigned to it. A scanned barcode counts what the system counts it as: 1 if
 * it is placed on a rack, 12 for a dozen barcode of big-warehouse stock.
 */
class StockOpnameService
{
    public const FOUND = 'found';
    public const WRONG_RACK = 'wrong_rack';
    public const NOT_IN_SYSTEM = 'not_in_system';
    public const MISSING = 'missing';
    public const DUPLICATE = 'duplicate';
    public const UNKNOWN = 'unknown';

    private array $cache = [];

    public function __construct(private readonly LegacyStockService $barcodes)
    {
    }

    /** @return array<string,array> combo key => model/color/size and pieces the system expects */
    public function expected(Rack $rack): array
    {
        $items = [];
        $add = function ($model, $color, $size, int $qty) use (&$items) {
            $key = "{$model?->id}|{$color?->id}|{$size?->id}";
            $items[$key] ??= $this->labels($model, $color, $size) + ['key' => $key, 'quantity' => 0];
            $items[$key]['quantity'] += $qty;
        };

        Product::with(['model', 'color', 'size'])->where('rack_id', $rack->id)->get()
            ->each(fn($p) => $add($p->model, $p->color, $p->size, 1));

        Inventory::with(['model', 'color', 'size'])->withSum('detail', 'quantity')->where('rack_id', $rack->id)->get()
            ->each(fn($i) => $add($i->model, $i->color, $i->size, (int) $i->detail_sum_quantity));

        return array_filter($items, fn($i) => $i['quantity'] > 0);
    }

    /**
     * What one scanned barcode is, seen from this rack.
     *
     * @return array{barcode:string,key:?string,model:?string,color:?string,size:?string,units:int,
     *               result:string,note:?string,covers:string[]}
     */
    public function identify(Rack $rack, string $barcode): array
    {
        $parts = explode('|', $barcode);
        $product = Product::withTrashed()->with(['model', 'color', 'size'])
            ->where('barcode', $barcode)
            // An active row wins over a deleted one with the same barcode.
            ->orderByRaw('deleted_at IS NOT NULL')
            ->first();

        $type = count($parts) === 7 ? strtoupper($parts[5]) : 'P';
        $model = $product?->model ?? (count($parts) === 7 ? $this->master(ProductModel::class, 'sku', $parts[2]) : null);
        $color = $product?->color ?? (count($parts) === 7 ? $this->master(Color::class, 'code', $parts[3]) : null);
        $size = $product?->size ?? (count($parts) === 7 ? $this->master(Size::class, 'code', $parts[4]) : null);

        if (!$model || !$color || !$size) {
            return [
                'barcode' => $barcode, 'key' => null, 'model' => null, 'color' => null, 'size' => null,
                'units' => 0, 'result' => self::UNKNOWN, 'note' => 'Barcode tidak dikenali', 'covers' => [],
            ];
        }

        $isBig = $rack->warehouse?->type === 'BESAR';
        [$result, $note] = [self::FOUND, null];
        if ($product?->trashed()) {
            [$result, $note] = [self::NOT_IN_SYSTEM, 'Menurut sistem sudah keluar / dihapus'];
        } elseif ($product && $product->rack_id !== null && $product->rack_id !== $rack->id) {
            $other = Rack::withTrashed()->find($product->rack_id);
            [$result, $note] = [self::WRONG_RACK, "Tercatat di rak {$other?->code}"];
        } elseif ($isBig && $product?->rack_id !== $rack->id) {
            // Big-warehouse stock is a quantity per item, assigned to a rack.
            $inventories = Inventory::with('rack')
                ->where(['model_id' => $model->id, 'color_id' => $color->id, 'size_id' => $size->id])
                ->get();
            if ($inventories->isEmpty()) {
                [$result, $note] = [self::NOT_IN_SYSTEM, 'Tidak ada stok gudang besar untuk barang ini'];
            } elseif (!$inventories->contains(fn($i) => $i->rack_id === $rack->id)) {
                $codes = $inventories->map(fn($i) => $i->rack?->code)->filter()->unique()->implode(', ');
                [$result, $note] = [self::WRONG_RACK, $codes !== '' ? "Stok barang ini tercatat di rak {$codes}" : 'Stok barang ini belum punya rak'];
            }
        } elseif (!$isBig && $product?->rack_id !== $rack->id) {
            [$result, $note] = [self::NOT_IN_SYSTEM, 'Belum tercatat di gudang kecil (belum dimutasi)'];
        }

        // A barcode placed on a rack is one item to the system (Cek Stok, Gudang
        // Kecil), whatever its letter. Elsewhere a dozen barcode is 12 pieces.
        $onRack = $product !== null && !$product->trashed() && $product->rack_id !== null;
        $isDozen = $type === 'D' && !$onRack;

        return $this->labels($model, $color, $size) + [
            'barcode' => $barcode,
            'key' => "{$model->id}|{$color->id}|{$size->id}",
            'units' => $isDozen ? LegacyStockService::DOZEN_QTY : 1,
            'result' => $result,
            'note' => $note,
            // Piece barcodes inside a dozen: scanning them too must not count twice.
            'covers' => $isDozen ? $this->barcodes->pieceBarcodesOf($barcode) : [],
        ];
    }

    /**
     * Compares everything scanned on a rack with the system.
     *
     * @param string[] $scanned
     * @return array{items:array<int,array>,barcodes:array<int,array>,system_qty:int,counted_qty:int}
     */
    public function compare(Rack $rack, array $scanned): array
    {
        $scanned = array_values(array_unique(array_filter(array_map('trim', $scanned))));
        $identified = array_map(fn($b) => $this->identify($rack, $b), $scanned);
        $covered = array_flip(array_merge([], ...array_column($identified, 'covers')));

        $items = [];
        foreach ($this->expected($rack) as $key => $item) {
            $items[$key] = [
                'model' => $item['model'], 'color' => $item['color'], 'size' => $item['size'],
                'system_qty' => $item['quantity'], 'counted_qty' => 0,
            ];
        }

        $barcodes = [];
        foreach ($identified as $row) {
            if (isset($covered[$row['barcode']])) {
                $barcodes[] = ['barcode' => $row['barcode'], 'result' => self::DUPLICATE, 'note' => 'Sudah terhitung dalam barcode lusinan'];
                continue;
            }
            if ($row['key'] !== null) {
                $items[$row['key']] ??= [
                    'model' => $row['model'], 'color' => $row['color'], 'size' => $row['size'],
                    'system_qty' => 0, 'counted_qty' => 0,
                ];
                $items[$row['key']]['counted_qty'] += $row['units'];
            }
            $barcodes[] = ['barcode' => $row['barcode'], 'result' => $row['result'], 'note' => $row['note']];
        }

        // Piece barcodes the system has on this rack that nobody scanned.
        $seen = array_flip($scanned) + $covered;
        foreach (Product::where('rack_id', $rack->id)->pluck('barcode') as $barcode) {
            if (!isset($seen[$barcode])) {
                $barcodes[] = ['barcode' => $barcode, 'result' => self::MISSING, 'note' => null];
            }
        }

        $items = array_values($items);
        usort($items, fn($a, $b) => [$a['model'], $a['color'], $a['size']] <=> [$b['model'], $b['color'], $b['size']]);

        return [
            'items' => $items,
            'barcodes' => $barcodes,
            'system_qty' => array_sum(array_column($items, 'system_qty')),
            'counted_qty' => array_sum(array_column($items, 'counted_qty')),
        ];
    }

    private function labels($model, $color, $size): array
    {
        return [
            'model' => $model?->name ?: ($model?->sku ?? '-'),
            'color' => $color?->name ?: ($color?->code ?? '-'),
            'size' => $size?->code ?: ($size?->name ?? '-'),
        ];
    }

    private function master(string $class, string $column, string $value)
    {
        return $this->cache["{$class}|{$value}"] ??= $class::where($column, $value)->first();
    }
}
