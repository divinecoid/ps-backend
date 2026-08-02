<?php

namespace Database\Seeders\Transactions;

use App\Models\MasterData\OnlineStore;
use App\Models\Transactions\Order;
use App\Enums\OrderStatus;
use Illuminate\Database\Seeder;
use Str;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $stores = OnlineStore::with('marketplace')->get();
        $defaultUser = \App\Models\MasterData\User::first();

        if (!$defaultUser) {
            $this->command->error('No users found! Please run UserSeeder first.');
            return;
        }

        $orders = [];

        $customerNames = [
            'Budi Santoso',
            'Siti Nurhaliza',
            'Ahmad Wijaya',
            'Dewi Lestari',
            'Rudi Hartono',
            'Ani Yudhoyono',
            'Joko Widodo',
            'Mega Sari',
            'Andi Pratama',
            'Rina Susanti',
            'Hendra Gunawan',
            'Agus Salim'
        ];

        $cities = ['Jakarta', 'Surabaya', 'Bandung', 'Medan', 'Semarang', 'Makassar'];
        $streets = ['Jl. Sudirman', 'Jl. Thamrin', 'Jl. Gatot Subroto', 'Jl. Ahmad Yani'];

        $statuses = [
            OrderStatus::PENDING,
            OrderStatus::READ,
            OrderStatus::READY_TO_SHIP,
            OrderStatus::RETURNED, // Ditambahkan agar ada sample order berstatus returned
        ];

        foreach ($stores as $store) {
            $ordersPerStore = rand(20, 30);

            for ($i = 0; $i < $ordersPerStore; $i++) {
                $status = $statuses[array_rand($statuses)];
                $itemCount = rand(1, 5);
                $uniqueItemCount = rand(1, $itemCount);

                $totalPrice = rand(100000, 1000000);
                $totalShipping = rand(10000, 50000);
                $totalAmount = $totalPrice + $totalShipping;

                $customerName = $customerNames[array_rand($customerNames)];
                $city = $cities[array_rand($cities)];
                $street = $streets[array_rand($streets)];

                $awbCode = 'AWB' . strtoupper(substr($store->marketplace->code, 0, 3)) . rand(100000000, 999999999);

                // ⬇️ SEMUA KOLOM DIDEFINISIKAN DI SINI
                $orderData = [
                    'id' => (string) Str::uuid(),
                    'awb_code' => $awbCode,
                    'online_store_id' => $store->id,
                    'marketplace_id' => $store->marketplace_id,
                    'order_sn' => strtoupper($store->marketplace->code) . '-' . now()->format('Ymd') . '-' . str_pad($i, 6, '0', STR_PAD_LEFT),

                    'status' => $status->value,
                    'item_count' => $itemCount,
                    'unique_item_count' => $uniqueItemCount,
                    'total_weight' => rand(500, 5000),
                    'total_price' => $totalPrice,
                    'total_shipping' => $totalShipping,
                    'total_amount' => $totalAmount,

                    'customer_name' => $customerName,
                    'customer_phone' => '08' . rand(1000000000, 9999999999),
                    'customer_address' => $street . ' No. ' . rand(1, 200) . ', ' . $city,

                    'preparist_user_id' => $defaultUser->id,

                    // ⬇️ KOLOM OPSIONAL → DEFAULT NULL
                    'read_at' => null,
                    'prepared_at' => null,
                    'prepare_duration' => null,
                    'readytoship_at' => null,
                    'readytoship_marketplace' => null,

                    'created_at' => now()->subDays(rand(0, 30)),
                    'updated_at' => now()->subDays(rand(0, 15)),
                ];

                // ⬇️ OVERRIDE SESUAI STATUS
                if ($status === OrderStatus::READ || $status === OrderStatus::READY_TO_SHIP) {
                    $orderData['read_at'] = now()->subDays(rand(0, 10));
                }

                if ($status === OrderStatus::READY_TO_SHIP) {
                    $orderData['prepared_at'] = now()->subDays(rand(0, 5));
                    $orderData['prepare_duration'] = rand(300, 3600);
                }

                if ($status === OrderStatus::READY_TO_SHIP) {
                    $orderData['readytoship_at'] = now()->subDays(rand(0, 3));
                    $orderData['readytoship_marketplace'] = now()->subDays(rand(0, 3));
                }

                $orders[] = $orderData;
            }
        }

        foreach (array_chunk($orders, 50) as $chunk) {
            Order::insert($chunk);
        }

        $this->command->info('Created ' . count($orders) . ' orders successfully!');
    }
}
