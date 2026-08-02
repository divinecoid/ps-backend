<?php

namespace App\Services;

use App\Models\MasterData\Marketplace;
use App\Models\MasterData\OnlineStore;
use LazopClient;
use LazopRequest;

class LazadaService
{
    protected OnlineStore $store;
    protected LazopClient $client;

    public function setStore(OnlineStore $store): self
    {
        $this->store = $store;
        $marketplace = Marketplace::findOrFail($store->marketplace_id);
        $this->client = new LazopClient($marketplace->base_api_url, $store->api_key, $store->client_secret);
        return $this;
    }

    public function refreshToken()
    {
        $request = new LazopRequest('/auth/token/refresh');
        $request->addApiParam('refresh_token', $this->store->refresh_token);
        $result = json_decode($this->client->execute($request, $this->store->access_token));

        $this->store->update([
            'access_token' => $result->access_token,
            'refresh_token' => $result->refresh_token,
            'access_token_expires_at' => now()->addSeconds($result->expires_in),
            'refresh_token_expires_at' => now()->addSeconds($result->refresh_expires_in),
        ]);

        return $result;
    }

    public function getOrderList($from, $to)
    {
        $request = new LazopRequest('/orders/get', 'GET');
        // $request->addApiParam('status', 'shipped');
        $request->addApiParam('created_after', $from->toIso8601String());
        $request->addApiParam('created_before', $to->toIso8601String());
        return json_decode($this->client->execute($request, $this->store->access_token));
    }

    public function getOrder($orderId)
    {
        $request = new LazopRequest('/order/get', 'GET');
        $request->addApiParam('order_id', $orderId);
        return json_decode($this->client->execute($request, $this->store->access_token));
    }

    public function getOrderItem($orderId)
    {
        $request = new LazopRequest('/order/items/get', 'GET');
        $request->addApiParam('order_id', $orderId);
        return json_decode($this->client->execute($request, $this->store->access_token));
    }

    public function getReceipt($orderItemId)
    {
        $request = new LazopRequest('/order/document/get', 'GET');
        $request->addApiParam('doc_type', 'shippingLabel');
        $request->addApiParam('order_item_ids', json_encode([$orderItemId]));
        return json_decode($this->client->execute($request, $this->store->access_token));
    }
}