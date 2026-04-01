<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterData\OnlineStore;
use App\Services\ShopeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MarketplaceAuthController extends Controller
{
    protected ShopeeService $shopeeService;

    public function __construct(ShopeeService $shopeeService)
    {
        $this->shopeeService = $shopeeService;
    }

    /**
     * Generic Refresh Token API for multiple marketplaces
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refreshToken(Request $request)
    {
        $request->validate([
            'online_store_id' => 'required|exists:mdx_online_stores,id',
        ]);

        try {
            $store = OnlineStore::with('marketplace')->findOrFail($request->online_store_id);
            $marketplaceAlias = strtolower($store->marketplace->alias ?? $store->marketplace->name);

            switch ($marketplaceAlias) {
                case 'shopee':
                    $this->shopeeService->setStore($store);
                    $result = $this->shopeeService->refreshAccessToken();
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Shopee token refreshed successfully.',
                        'data' => $result
                    ]);

                // Future cases can be added here
                // case 'lazada':
                //     ...
                //     break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => "Marketplace '{$marketplaceAlias}' is not supported for auto refresh yet."
                    ], 400);
            }

        } catch (\Exception $e) {
            Log::error("Marketplace Refresh Token Error ({$request->online_store_id}): " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
