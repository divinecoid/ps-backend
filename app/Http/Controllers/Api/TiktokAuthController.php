<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiFilterTrait;
use App\Models\MasterData\OnlineStore;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Http\Traits\HtmlSuccessPageTrait;
use Illuminate\Support\Facades\Http;

class TiktokAuthController extends Controller
{
    use ApiFilterTrait;
    use HtmlSuccessPageTrait;
    /**
     * STEP 1
     * Redirect user ke halaman authorization Tiktok
     */
    public function redirectToTiktok($id)
    {
        $store = OnlineStore::findOrFail($id);

        // OAuth URL resmi yang dipakai Seller Authorization
        $authUrl = "https://services.tiktokshop.com/open/authorize?" . http_build_query([
            "service_id" => $store->client_id,
            "state"      => $store->id,
        ]);

        return redirect($authUrl);
    }

    /**
     * STEP 2
     * Callback → dapat CODE → Tukar dengan Access Token
     */
    public function handleCallback(Request $request)
    {
        $store = OnlineStore::findOrFail($request->query("state"));

        $code = $request->query("code");
        if (!$code) {
            return $this->errorResponse(400, 'Authorization code not found in callback');
        }

        // TikTok token endpoint
        $url = "https://auth.tiktok-shops.com/api/v2/token/get";

        // Query parameters
        $params = [
            "app_key"     => $store->api_key,
            "app_secret"  => $store->client_secret,
            "auth_code"   => $code,
            "grant_type"  => "authorized_code",
        ];

        // Send GET request
        $response = Http::get($url, $params);

        if (!$response->ok()) {
            return $this->errorResponse(500, 'HTTP request failed');
        }

        $json = $response->json();

        // TikTok requirement: code must be === 0
        if (!isset($json["code"]) || $json["code"] !== 0) {
            return $this->errorResponse(400, 'Failed to obtain access token');
        }

        $data = $json["data"];

        // Save token to DB
        $store->update([
            "access_token"  => $data["access_token"],
            "refresh_token" => $data["refresh_token"],
            "access_token_expires_at"  => Carbon::createFromTimestamp($data["access_token_expire_in"]),
            "refresh_token_expires_at" => Carbon::createFromTimestamp($data["refresh_token_expire_in"]),
        ]);

        // Return success HTML
        return response(
            $this->successHtmlPage(
                "Silakan menutup halaman ini dan cek data terupdate pada online store tersebut."
            )
        )->header("Content-Type", "text/html");
    }

    /**
     * STEP 3
     * Refresh Token → dapat Token → Replace di DB
     */
    public function refreshToken($id)
    {
        $store = OnlineStore::findOrFail($id);

        if (!$store->refresh_token) {
            return $this->errorResponse(400, 'No refresh token available');
        }

        // TikTok API endpoint
        $url = "https://auth.tiktok-shops.com/api/v2/token/refresh";

        // Query params
        $params = [
            "app_key"       => $store->api_key,
            "app_secret"    => $store->client_secret,
            "refresh_token" => $store->refresh_token,
            "grant_type"    => "refresh_token",
        ];

        // Send GET request
        $response = Http::get($url, $params);

        // If there is error
        if (!$response->ok()) {
            return $this->errorResponse(500, 'HTTP request failed');
        }

        $json = $response->json();

        if (!isset($json["code"]) || $json["code"] !== 0) {
            return $this->errorResponse(400, 'Failed to refresh access token');
        }

        $data = $json["data"];

        // Update database
        $store->update([
            "access_token"              => $data["access_token"],
            "refresh_token"             => $data["refresh_token"],
            "access_token_expires_at"   => Carbon::createFromTimestamp($data["access_token_expire_in"]),
            "refresh_token_expires_at"  => Carbon::createFromTimestamp($data["refresh_token_expire_in"]),
        ]);

        // Return HTML sukses
        return $this->successResponse($data);
    }
}
