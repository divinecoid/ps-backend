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

    public function getOrder($id)
    {
        $onlinestore = OnlineStore::with('marketplace')->find($id);
        $marketplace = Marketplace::find($onlinestore->marketplace_id);

        $c = new LazopClient($marketplace->base_api_url, $onlinestore->api_key, $onlinestore->client_secret);

        // $request = new LazopRequest('/seller/get', 'GET');
        $request = new LazopRequest('/orders/get', 'GET');
        $request->addApiParam('status', 'shipped');
        return json_decode($c->execute($request, $onlinestore->access_token));
    }

    public function pickupOrder()
    {

    }

    public function getReceipt()
    {

    }



}