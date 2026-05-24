<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterData\OnlineStore;
use App\Services\LazadaService;

class LazadaController extends Controller
{
    public function refreshToken($id, LazadaService $lazada)
    {
        $store = OnlineStore::findOrFail($id);
        return $lazada->setStore($store)->refreshToken();
    }

    public function getOrderList($id, LazadaService $lazada)
    {
        $store = OnlineStore::findOrFail($id);
        return $lazada->setStore($store)->getOrderList(now()->subDay(), now());
    }

    public function getOrder($id, $orderId, LazadaService $lazada)
    {
        $store = OnlineStore::findOrFail($id);
        return $lazada->setStore($store)->getOrder($orderId);
    }

    public function getReceipt($id, $orderItemId, LazadaService $lazada)
    {
        $store = OnlineStore::findOrFail($id);
        return $lazada->setStore($store)->getReceipt($orderItemId);
    }
}