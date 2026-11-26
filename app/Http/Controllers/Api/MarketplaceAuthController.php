<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterData\OnlineStore;
use App\Services\LazadaService;
use App\Services\ShopeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

class MarketplaceAuthController extends Controller
{
    protected ShopeeService $shopeeService;
    protected LazadaService $lazadaService;

    public function __construct(ShopeeService $shopeeService, LazadaService $lazadaService)
    {
        $this->shopeeService = $shopeeService;
        $this->lazadaService = $lazadaService;
    }

    /**
     * Returns a short-lived signed path that starts the marketplace authorization.
     */
    public function connectUrl(Request $request)
    {
        $request->validate([
            'online_store_id' => 'required|exists:mdx_online_stores,id',
        ]);

        $store = OnlineStore::with('marketplace')->findOrFail($request->online_store_id);
        $alias = strtolower($store->marketplace->alias ?? $store->marketplace->name ?? '');
        $route = "oauth.login.{$alias}";

        if (!Route::has($route)) {
            return response()->json([
                'success' => false,
                'message' => "Marketplace '{$alias}' belum mendukung Connect.",
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                // Signed relative to the path so it stays valid behind a reverse proxy
                'path' => URL::temporarySignedRoute($route, now()->addMinutes(5), ['id' => $store->id], false),
            ],
        ]);
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
                case 'shopee_sandbox':
                    $this->shopeeService->setStore($store);
                    $result = $this->shopeeService->refreshAccessToken();

                    return response()->json([
                        'success' => true,
                        'message' => 'Shopee token refreshed successfully.',
                        'data' => $result
                    ]);

                case 'lazada':
                    $result = $this->lazadaService->setStore($store)->refreshToken();

                    return response()->json([
                        'success' => true,
                        'message' => 'Lazada token refreshed successfully.',
                        'data' => $result
                    ]);

                case 'tiktok_shop':
                case 'tiktok-shop':
                    $tiktokController = app(\App\Http\Controllers\Api\TiktokAuthController::class);
                    return $tiktokController->refreshToken($store->id);
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
