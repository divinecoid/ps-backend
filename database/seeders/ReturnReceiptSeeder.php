<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReturnReceiptSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $returnedOrders = \App\Models\Transactions\Order::where('status', 'returned')->get();

        if ($returnedOrders->isEmpty()) {
            $this->command->warn('No orders with status "returned" found. Make sure you ran OrderSeeder first.');
            return;
        }

        $count = 0;
        foreach ($returnedOrders as $order) {
            // Check if return receipt already exists for this order
            $exists = \App\Models\Transactions\ReturnReceipt::where('order_id', $order->id)->exists();
            if ($exists) {
                continue;
            }

            // Create return receipt
            $receipt = \App\Models\Transactions\ReturnReceipt::create([
                'order_id' => $order->id,
                'awb_code' => $order->awb_code ?? 'AWB-RET-' . rand(100000, 999999),
                'return_status' => 'pending', // Seed pending retur
                'received_at' => null,
                'received_by' => null,
                'notes' => 'Menunggu kurir ekspedisi mengembalikan barang ke gudang.',
            ]);

            // Create placeholders return details
            foreach ($order->order_items as $item) {
                for ($i = 0; $i < $item->quantity_purchased; $i++) {
                    \App\Models\Transactions\ReturnReceiptDetail::create([
                        'return_receipt_id' => $receipt->id,
                        'order_item_id' => $item->id,
                        'is_received' => false,
                    ]);
                }
            }
            $count++;
        }

        $this->command->info("Created {$count} pending return receipts successfully.");
    }
}
