<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LazadaService;
use App\Models\MasterData\OnlineStore;
use Illuminate\Support\Facades\Log;

class FetchLazadaOrders extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:fetch-orders {--days=1 : Number of days to look back}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch orders from Lazada and process them';

    public function handle(LazadaService $lazada)
    {
        $stores = OnlineStore::whereHas('marketplace', function ($q) {
            $q->where('name', 'like', '%Lazada%');
        })->where('is_active', true)->get();

        /** @var \App\Models\MasterData\OnlineStore $store */
        foreach ($stores as $store) {
            $response = $lazada->setStore($store)->getOrderList(now()->subDay(), now());
            $orders = $response->data->orders ?? [];

            foreach ($orders as $order) {
                $detail = $lazada->getOrder($order->order_id);
                $this->saveOrder($detail->data, $store);
            }
        }
    }

    private function saveOrder($detail, $store)
    {
        try {

            Log::info('========== LAZADA ORDER ==========', [
                'store_id' => $store->id,
                'store_name' => $store->store_name,
                'order_id' => $detail->order_id ?? null,
                'order_number' => $detail->order_number ?? null,
                'status' => $detail->statuses ?? null,
                'customer' => [
                    'name' => $detail->customer_first_name ?? null,
                    'last_name' => $detail->customer_last_name ?? null,
                ],
                'price' => $detail->price ?? null,
                'shipping_fee' => $detail->shipping_fee ?? null,
                'created_at' => $detail->created_at ?? null,
                'raw' => $detail
            ]);

            if (!empty($detail->items)) {

                foreach ($detail->items as $item) {

                    Log::info('LAZADA ORDER ITEM', [
                        'order_item_id' => $item->order_item_id ?? null,
                        'name' => $item->name ?? null,
                        'sku' => $item->sku ?? null,
                        'quantity' => $item->quantity ?? null,
                        'paid_price' => $item->paid_price ?? null,
                        'status' => $item->status ?? null,
                        'raw' => $item
                    ]);
                }
            }

            $this->info(
                'Logged order: ' .
                ($detail->order_number ?? $detail->order_id)
            );

        } catch (\Exception $e) {

            Log::error('Failed logging Lazada order', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $detail
            ]);

            $this->error($e->getMessage());
        }
    }

}