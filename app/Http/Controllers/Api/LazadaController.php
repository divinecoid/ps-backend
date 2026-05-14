<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiFilterTrait;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Marketplace;
use App\Models\MasterData\OnlineStore;
use LazopClient;
use LazopRequest;

class LazadaController extends Controller
{

    /**
     * $id = online store id
     */
    use CrudTrait, ApiFilterTrait;

    private function getClient($onlinestore)
    {
        $marketplace = Marketplace::find($onlinestore->marketplace_id);
        return new LazopClient($marketplace->base_api_url, $onlinestore->api_key, $onlinestore->client_secret);
    }

    public function refreshToken($id)
    {
        $onlinestore = OnlineStore::with('marketplace')->find($id);
        $c = $this->getClient($onlinestore);
        $request = new LazopRequest('/auth/token/refresh');
        $request->addApiParam('refresh_token', $onlinestore->refresh_token);
        $result = json_decode($c->execute($request, $onlinestore->access_token));

        $onlinestore->update([
            'access_token' => $result->access_token,
            'refresh_token' => $result->refresh_token,
            'access_token_expires_at' => now()->addSeconds($result->expires_in),
            'refresh_token_expires_at' => now()->addSeconds($result->refresh_expires_in),
        ]);

        return $result;
    }

    public function getOrderList($id)
    {
        $onlinestore = OnlineStore::with('marketplace')->find($id);
        $c = $this->getClient($onlinestore);

        $request = new LazopRequest('/orders/get', 'GET');
        $request->addApiParam('status', 'shipped');
        return json_decode($c->execute($request, $onlinestore->access_token));
    }

    public function getOrder($id, $orderId)
    {
        $onlinestore = OnlineStore::with('marketplace')->find($id);
        $c = $this->getClient($onlinestore);

        $request = new LazopRequest('/order/get', 'GET');
        $request->addApiParam('order_id', $orderId);
        return json_decode($c->execute($request, $onlinestore->access_token));

    }

    public function pickupOrder($id, $orderId)
    {
        $onlinestore = OnlineStore::with('marketplace')->find($id);
        $c = $this->getClient($onlinestore);

    }

    public function getReceipt($id, $orderId)
    {
        $onlinestore = OnlineStore::with('marketplace')->find($id);
        $c = $this->getClient($onlinestore);

        $request = new LazopRequest('/order/document/get', 'GET');
        $request->addApiParam('doc_type', 'shippingLabel');
        $request->addApiParam('order_item_ids', json_encode([$orderId]));
        return json_decode($c->execute($request, $onlinestore->access_token));
    }

}