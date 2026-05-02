<?php

namespace Database\Seeders\Transactions;

use App\Models\MasterData\Product;
use App\Models\Transactions\Order;
use App\Models\Transactions\OrderItem;
use Illuminate\Database\Seeder;
use Str;

class OrderItemSeeder extends Seeder
{
    public function run(): void
    {
        // Fetch all orders
        $orders = Order::all();

        // Fetch all products with their relationships
        $products = Product::with(['model', 'rack'])->get();

        if ($products->isEmpty()) {
            $this->command->error('No products found! Please run ProductSeeder first.');
            return;
        }

        $orderItems = [];
        $orderItemCounter = 0;

        foreach ($orders as $order) {
            // Create 1-5 order items per order (matching the item_count)
            $itemsToCreate = $order->item_count ?? rand(1, 5);

            // Randomly select products for this order
            $selectedProducts = $products->random(min($itemsToCreate, $products->count()));

            foreach ($selectedProducts as $product) {
                // Parse barcode to extract information
                // Barcode format: {CMT_CODE}|{Timestamp}|{MODELS_SKU}|{COLORS_CODE}|{SIZE_CODE}|{D}|{P}
                $barcodeParts = explode('|', $product->barcode);

                if (count($barcodeParts) >= 7) {
                    $cmtCode = $barcodeParts[0];
                    $modelSku = $barcodeParts[2];
                    $colorCode = $barcodeParts[3];
                    $sizeCode = $barcodeParts[4];

                    // Map color codes to Indonesian names
                    $colorNames = [
                        'RED',
                        'GREEN',
                        'BLUE',
                        'YELLOW',
                        'BLACK',
                        'GRAY',
                        'WHITE',
                    ];

                    // Map size codes to Indonesian names
                    $sizeNames = [
                        'XS',
                        'S',
                        'M',
                        'L',
                        'XL',
                        '2XL',
                        '3XL'
                    ];

                    // Map model SKU to product names
                    $modelNames = [
                        'LP',
                        'LC',
                        'SP',
                        'SC'
                    ];
                    $colorIndex = array_search($colorCode, $colorNames, true);
                    $sizeIndex = array_search($sizeCode, $sizeNames, true);
                    $modelIndex = array_search($modelSku, $modelNames, true);

                    $colorName = $colorIndex !== false ? $colorNames[$colorIndex] : $colorCode;
                    $sizeName = $sizeIndex !== false ? $sizeNames[$sizeIndex] : $sizeCode;
                    $modelName = $modelIndex !== false ? $modelNames[$modelIndex] : $modelSku;

                    // Generate item name
                    $itemName = "{$modelName} - {$colorName} - {$sizeName}";

                    // Generate realistic pricing
                    $basePrice = rand(50000, 200000);
                    $discountPercent = rand(10, 20);
                    $discountedPrice = $basePrice - ($basePrice * $discountPercent / 100);

                    // Quantity purchased
                    $quantityPurchased = rand(1, 3);

                    // Generate unique order_item_id
                    $orderItemId = strtoupper(Str::random(10));

                    $orderItems[] = [
                        'id' => (string) Str::uuid(),
                        'order_id' => $order->id,
                        'order_item_id' => $orderItemId,
                        'sku' => $modelSku,
                        'item_name' => $itemName,
                        'color' => $colorName,
                        'size' => $sizeName,
                        'product_id' => $product->id,
                        'price' => $basePrice,
                        'discounted_price' => $discountedPrice,
                        'quantity_purchased' => $quantityPurchased,
                        'item_prepared_at' => null, // Will be set when item is prepared
                        'notes' => null,
                        'created_at' => $order->created_at,
                        'updated_at' => $order->updated_at,
                    ];

                    $orderItemCounter++;
                }
            }
        }

        // Insert order items in chunks for better performance
        foreach (array_chunk($orderItems, 100) as $chunk) {
            OrderItem::insert($chunk);
        }

        $this->command->info('Created ' . count($orderItems) . ' order items successfully!');
    }
}
