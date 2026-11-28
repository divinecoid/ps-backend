<?php

namespace App\Services;

use App\Models\MasterData\Color;
use App\Models\MasterData\ProductModel;
use App\Models\MasterData\Size;
use App\Support\MarketplaceSku;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Goods that left the warehouse for online orders (orders scanned outbound),
 * counted per marketplace, store, model, colour and size according to the
 * order item's SKU.
 */
class OnlineOutboundReport
{
    /** When the order was scanned out. */
    private const OUTBOUND_AT = 'COALESCE(o.readytoship_at, o.updated_at)';

    /**
     * @param array{date_from?:?string,date_to?:?string,marketplace_id?:?string,online_store_id?:?string,search?:?string} $filters
     *        dates are WIB calendar days
     * @return array{rows:array<int,array>,summary:array{qty:int,orders:int}}
     */
    public function build(array $filters): array
    {
        $query = DB::table('trx_order_items as i')
            ->join('trx_orders as o', 'o.id', '=', 'i.order_id')
            ->leftJoin('mdx_online_stores as s', 's.id', '=', 'o.online_store_id')
            ->leftJoin('mdx_marketplaces as m', 'm.id', '=', DB::raw('COALESCE(o.marketplace_id, s.marketplace_id)'))
            ->whereNull('i.deleted_at')
            ->whereNull('o.deleted_at')
            ->where('o.is_outbounded', true)
            // A cancelled order's goods go back on the shelf.
            ->where('o.status', '!=', 'cancelled')
            ->select([
                'i.sku', 'i.color', 'i.size', 'i.item_name', 'i.quantity_purchased',
                'o.id as order_id', 's.store_name', 'm.name as marketplace_name',
            ]);

        if (!empty($filters['marketplace_id'])) {
            $query->where('m.id', $filters['marketplace_id']);
        }
        if (!empty($filters['online_store_id'])) {
            $query->where('o.online_store_id', $filters['online_store_id']);
        }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $field => $operator) {
            if (!empty($filters[$field])) {
                $day = Carbon::parse($filters[$field], 'Asia/Jakarta');
                $moment = $field === 'date_from' ? $day->startOfDay() : $day->endOfDay();
                $query->whereRaw(self::OUTBOUND_AT . " {$operator} ?", [$moment->utc()->format('Y-m-d H:i:s')]);
            }
        }

        $models = $this->lookup(ProductModel::withTrashed()->get(['sku', 'name', 'deleted_at']), 'sku');
        $colors = $this->lookup(Color::withTrashed()->get(['code', 'name', 'deleted_at']), 'code', 'name');
        $sizes = $this->lookup(Size::withTrashed()->get(['code', 'name', 'deleted_at']), 'code', 'name');

        $rows = [];
        $orders = [];
        foreach ($query->cursor() as $item) {
            $pieces = MarketplaceSku::pieces(trim((string) $item->sku), $item->color)
                // No usable SKU: keep the item visible instead of dropping it.
                ?: [['sku' => '', 'color' => (string) $item->color]];

            foreach ($pieces as $piece) {
                $sku = trim($piece['sku']);
                $model = $models[mb_strtolower($sku)] ?? null;
                $color = trim($piece['color']);
                $color = $colors[mb_strtolower($color)]->name ?? $color;
                $size = trim((string) $item->size);
                $size = $sizes[mb_strtolower($size)]->code ?? $size;

                $row = [
                    'marketplace' => $item->marketplace_name ?? '-',
                    'store' => $item->store_name ?? '-',
                    'sku' => $sku !== '' ? mb_strtoupper($sku) : '-',
                    'model' => $model?->name ?? ($sku !== '' ? $sku : (string) $item->item_name),
                    'model_registered' => $model !== null,
                    'color' => $color !== '' ? $color : '-',
                    'size' => $size !== '' ? $size : '-',
                ];
                $key = mb_strtolower(implode('|', [$row['marketplace'], $row['store'], $row['sku'], $row['model'], $row['color'], $row['size']]));
                $rows[$key] ??= $row + ['qty' => 0, 'order_ids' => []];
                $rows[$key]['qty'] += max(0, (int) $item->quantity_purchased);
                $rows[$key]['order_ids'][$item->order_id] = true;
            }
        }

        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $result = [];
        $total = 0;
        foreach ($rows as $row) {
            if ($search !== '' && !str_contains(mb_strtolower("{$row['sku']} {$row['model']} {$row['color']} {$row['size']}"), $search)) {
                continue;
            }
            $orders += $row['order_ids'];
            $total += $row['qty'];
            $row['orders'] = count($row['order_ids']);
            unset($row['order_ids']);
            $result[] = $row;
        }

        $sizeOrder = Size::withTrashed()->orderBy('created_at')->pluck('code')->map(fn($c) => mb_strtolower($c))->flip();
        usort($result, fn($a, $b) => [$a['marketplace'], $a['store'], $a['model'], $a['color'], $sizeOrder[mb_strtolower($a['size'])] ?? PHP_INT_MAX, $a['size']]
            <=> [$b['marketplace'], $b['store'], $b['model'], $b['color'], $sizeOrder[mb_strtolower($b['size'])] ?? PHP_INT_MAX, $b['size']]);

        return ['rows' => $result, 'summary' => ['qty' => $total, 'orders' => count($orders)]];
    }

    /** Case-insensitive index by the given columns; active records win over deleted ones. */
    private function lookup($records, string ...$columns): array
    {
        $index = [];
        foreach ($records->sortBy(fn($r) => $r->deleted_at === null ? 1 : 0) as $record) {
            foreach (array_reverse($columns) as $column) {
                $index[mb_strtolower(trim((string) $record->{$column}))] = $record;
            }
        }
        unset($index['']);

        return $index;
    }
}
