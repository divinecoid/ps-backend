<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterData\OnlineStore;
use App\Services\ShopeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopeeAuthController extends Controller
{
    protected $shopeeService;

    public function __construct(ShopeeService $shopeeService)
    {
        $this->shopeeService = $shopeeService;
    }

    /**
     * Generate Shopee Login URL
     * Updates store attributes if provided, then generates the URL.
     */
    public function generateAuthUrl(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:mdx_online_stores,id',
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',
            'redirect_uri' => 'nullable|url',
        ]);

        $store = OnlineStore::findOrFail($request->id);

        // Update attributes if provided ("simpen di db deh atributnya")
        $updates = [];
        if ($request->has('client_id')) {
            $updates['client_id'] = $request->client_id;
        }
        if ($request->has('client_secret')) {
            $updates['client_secret'] = $request->client_secret;
        }
        if ($request->has('redirect_uri')) {
            $updates['redirect_uri'] = $request->redirect_uri;
        }

        if (!empty($updates)) {
            $store->update($updates);
        }

        try {
            $url = $this->shopeeService->generateAuthUrl($store);
            return response()->json([
                'success' => true,
                'url' => $url
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
