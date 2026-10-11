<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiFilterTrait;
use App\Models\MasterData\Inventory;
use App\Models\MasterData\Product;
use App\Models\MasterData\Rack;
use Illuminate\Http\Request;

class RackStockController extends Controller
{
    use ApiFilterTrait;

    /**
     * Stok yang ada di satu rak berdasarkan kode rak (hasil scan QR Code rak).
     * Menggabungkan stok gudang kecil (produk per barcode) dan gudang besar (inventory).
     */
    public function byCode(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $rack = Rack::with('warehouse')->where('code', $request->input('code'))->first();
        if (!$rack) {
            return $this->errorResponse(404, 'Rak tidak ditemukan');
        }

        $items = [];
        $add = function ($model, $color, $size, int $qty) use (&$items) {
            $key = "{$model?->id}|{$color?->id}|{$size?->id}";
            $items[$key] ??= [
                'model' => $model?->name,
                'color' => $color?->name,
                'size' => $size?->name,
                'quantity' => 0,
            ];
            $items[$key]['quantity'] += $qty;
        };

        Product::with(['model', 'color', 'size'])
            ->where('rack_id', $rack->id)
            ->get()
            ->each(fn($p) => $add($p->model, $p->color, $p->size, 1));

        Inventory::with(['model', 'color', 'size'])
            ->withSum('detail', 'quantity')
            ->where('rack_id', $rack->id)
            ->get()
            ->each(fn($i) => $add($i->model, $i->color, $i->size, (int) $i->detail_sum_quantity));

        $items = collect($items)
            ->filter(fn($i) => $i['quantity'] > 0)
            ->sortBy([['model', 'asc'], ['color', 'asc'], ['size', 'asc']])
            ->values();

        return $this->successResponse([
            'rack' => [
                'id' => $rack->id,
                'code' => $rack->code,
                'name' => $rack->name,
                'warehouse' => $rack->warehouse?->name,
            ],
            'items' => $items,
            'total' => $items->sum('quantity'),
        ]);
    }
}
