<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Http\Traits\MarketplaceApiTrait;
use App\Models\Transactions\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    use CrudTrait, MarketplaceApiTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'awb_code' => $data->awb_code,
            'read_at' => $data->read_at,
            'prepared_at' => $data->prepared_at,
            'prepare_duration' => $data->prepare_duration,
            'readtoship_at' => $data->readtoship_at,
            'readtoship_marketplace' => $data->readtoship_marketplace,
            'online_store_id' => $data->online_store_id,
            'item_count' => $data->item_count,
            'unique_item_count' => $data->unique_item_count,
            'status' => $data->status,
            'total_weight' => $data->total_weight,
            'total_price' => $data->total_price,
            'total_shipping' => $data->total_shipping,
            'total_amount' => $data->total_amount,
            'preparist_user_id' => $data->preparist_user_id,
            'customer_name' => $data->customer_name,
            'customer_phone' => $data->customer_phone,
            'customer_address' => $data->customer_address,
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            Order::class,
            [],
            [],
            $this->structure()
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            Order::class,
            $id,
            ['order_items', 'order_note'],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseStore(
            $request,
            Order::class,
            [
                'awb_code' => 'nullable|string|unique:trx_orders,awb_code|max:255',
                'read_at' => 'nullable|date',
                'prepared_at' => 'nullable|date',
                'prepare_duration' => 'nullable|integer|min:0',
                'readytoship_at' => 'nullable|date',
                'readytoship_marketplace' => 'nullable|string|max:255',
                'online_store_id' => 'required|exists:mdx_online_stores,id',
                'item_count' => 'required|integer|min:1',
                'unique_item_count' => 'required|integer|min:1',
                'status' => 'required|in:pending,read,prepared,ready_to_ship,shipped,delivered,cancelled,returned',
                'total_weight' => 'nullable|numeric|min:0',
                'total_price' => 'required|numeric|min:0',
                'total_shipping' => 'required|numeric|min:0',
                'total_amount' => 'required|numeric|min:0',
                'preparist_user_id' => 'required|exists:users,id',
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'nullable|string|max:50',
                'customer_address' => 'nullable|string|max:500',
            ],
            null
        );
    }

    public function update(Request $request, $id)
    {
        return $this->baseUpdate(
            $request,
            Order::class,
            $id,
            [
                'awb_code' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('trx_orders', 'awb_code')->ignore($id)
                ],
                'read_at' => 'nullable|date',
                'prepared_at' => 'nullable|date',
                'prepare_duration' => 'nullable|integer|min:0',
                'readytoship_at' => 'nullable|date',
                'readytoship_marketplace' => 'nullable|string|max:255',
                'online_store_id' => 'required|exists:mdx_online_stores,id',
                'item_count' => 'required|integer|min:1',
                'unique_item_count' => 'required|integer|min:1',
                'status' => 'required|in:pending,read,prepared,ready_to_ship,shipped,delivered,cancelled,returned',
                'total_weight' => 'nullable|numeric|min:0',
                'total_price' => 'required|numeric|min:0',
                'total_shipping' => 'required|numeric|min:0',
                'total_amount' => 'required|numeric|min:0',
                'preparist_user_id' => 'required|exists:users,id',
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'nullable|string|max:50',
                'customer_address' => 'nullable|string|max:500',
            ],
            null
        );
    }

    public function destroy($id)
    {
        return $this->baseDelete(
            Order::class,
            $id
        );
    }

    public function restore($id)
    {
        return $this->baseRestore(Order::class, $id);
    }

    public function getLazadaOrder($id)
    {
        // $token = config('marketplace.lazada.access_token');
        $access_token = '50000700931sNGv7jlNVPtDZZI0ipQDuQg9jDRBlSTF1fb7d45egGMiXDHpIoTos';
        $baseUrl = config('marketplace.lazada.base_url');
        $app_key = config('marketplace.lazada.app_key');
        $timestamp = (int) (microtime(true) * 1000);

        $signinmethod = 'sha256';
        $sign = '31BB8D408663C84BBE6D2431147CD98BEC909BB6F185F2ACC098C36BD5352846';

        $response = $this->getMarketplaceData(
            $baseUrl,
            '/order/get',
            ['order_id' => (int)$id, 'app_key' => $app_key, 'timestamp' => $timestamp, 'sign_method' => $signinmethod, 'sign' => $sign],
            // $token
        );

        return response()->json($response, $response['success'] ? 200 : 500);
    }
}
