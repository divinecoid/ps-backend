<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiFilterTrait;
use App\Models\MasterData\OnlineStore;
use Illuminate\Http\Request;
use LazopClient;
use LazopRequest;
use Carbon\Carbon;
use App\Http\Traits\HtmlSuccessPageTrait;


class LazadaAuthController extends Controller
{
    use ApiFilterTrait;
    use HtmlSuccessPageTrait;
    /**
     * STEP 1
     * Redirect user ke halaman authorization Lazada
     */
    public function redirectToLazada($id)
    {
        $store = OnlineStore::findOrFail($id);

        // OAuth URL resmi yang dipakai Seller Authorization
        $authUrl = "https://auth.lazada.com/oauth/authorize?" . http_build_query([
            "response_type" => "code",
            "force_auth"    => "true",
            "redirect_uri"  => $store->redirect_uri,
            "client_id"     => $store->api_key,  // = app_key
            "state"         => $store->id,
        ]);

        return redirect($authUrl);
    }

    /**
     * STEP 2
     * Callback → dapat CODE → Tukar dengan Access Token via SDK
     */
    public function handleCallback(Request $request)
    {
        $store = OnlineStore::findOrFail($request->query("state"))->with('marketplace');
        $code = $request->query("code");
        if (!$code) {
            return $this->errorResponse(400, 'Authorization code not found in callback');
        }
        // SDK CLIENT
        $client = new LazopClient(
            $store->marketplace->base_api_url,
            $store->api_key,
            $store->client_secret
        );
        // Lazada Token Request
        $req = new LazopRequest('/auth/token/create');
        $req->addApiParam("code", $code);
        // Execute Request
        $response = json_decode($client->execute($req), true);
        if (!isset($response["access_token"])) {
            return $this->errorResponse(400, 'Failed to obtain access token');
        }
        // Save token
        $store->update([
            "access_token"  => $response["access_token"],
            "refresh_token" => $response["refresh_token"] ?? null,
            "access_token_expires_at"    => Carbon::now()->addSeconds($response["expires_in"]),
        ]);
        // RETURN SIMPLE HTML PAGE
        return response(
            $this->successHtmlPage("Silakan menutup halaman ini dan cek data terupdate pada online store tersebut.")
        )->header("Content-Type", "text/html");
    }

    /**
     * STEP 3
     * Refresh Token → dapat Token → Replace di DB
     */
    public function refreshToken($id)
    {
        $store = OnlineStore::findOrFail($id)->with('marketplace');
        if (!$store->refresh_token) {
            return $this->errorResponse(400, 'No refresh token available');
        }
        $client = new LazopClient(
            $store->marketplace->base_api_url,
            $store->api_key,
            $store->client_secret
        );
        $req = new LazopRequest('/auth/token/refresh');
        $req->addApiParam("refresh_token", $store->refresh_token);
        $response = json_decode($client->execute($req), true);
        if (!isset($response["access_token"])) {
            return $this->errorResponse(400, 'Failed to refresh access token');
        }
        $store->update([
            "access_token"  => $response["access_token"],
            "refresh_token" => $response["refresh_token"] ?? $store->refresh_token,
            "expires_at"    => Carbon::now()->addSeconds($response["expires_in"]),
        ]);
        return $this->successResponse($response);
    }
}
