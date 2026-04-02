<?php

namespace App\Console\Commands;

use App\Models\Transactions\Order;
use App\Models\Transactions\OrderItem;
use Illuminate\Console\Command;

class RecalculateOrderItemCounts extends Command
{
    protected $signature = 'orders:recalculate-counts';
    protected $description = 'Recalculate item_count (total quantity) and unique_item_count for all orders';

    public function handle()
    {
        $this->info('Starting to recalculate order item counts...');

        $orders = Order::all();
        $updated = 0;

        foreach ($orders as $order) {
            $items = OrderItem::where('order_id', $order->id)->get();

            $totalQuantity = $items->sum('quantity_purchased');

            $uniqueCount = $items->count();

            if ($order->item_count !== $totalQuantity || $order->unique_item_count !== $uniqueCount) {
                $order->update([
                    'item_count' => $totalQuantity,
                    'unique_item_count' => $uniqueCount,
                ]);
                $updated++;
            }
        }

        $this->info("Recalculated counts for $updated orders.");
    }
}

// This command can be run using: php artisan orders:recalculate-counts
// It will update the trx_orders table's item_count and unique_item_count fields based on the related trx_order_items data.
