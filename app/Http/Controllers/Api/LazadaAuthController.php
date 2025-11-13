<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterData\OnlineStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class LazadaAuthController extends Controller
{
    /**
     * Redirect the user to Lazada's authorization page
     * to obtain the Authorization Code.
     */
    public function redirectToLazada($id)
    {
        $store = OnlineStore::findOrFail($id);

        $authUrl = "https://auth.lazada.com/oauth/authorize?"
            . "response_type=code"
            . "&force_auth=true"
            . "&redirect_uri=" . urlencode($store->redirect_uri)
            . "&client_id=" . $store->api_key;

        return redirect($authUrl);
    }

    /**
     * Lazada redirects back to this method with the "code".
     * We capture the code and exchange it for an access token.
     */
    public function handleCallback(Request $request, $id)
    {
        $store = OnlineStore::findOrFail($id);

        $code = $request->query('code');

        if (!$code) {
            return response()->json([
                'error' => 'Authorization code not found in callback'
            ], 400);
        }

        return $this->exchangeCodeForToken($store, $code);
    }

    /**
     * Exchange the Authorization Code for an
     * Access Token and Refresh Token.
     */
    private function exchangeCodeForToken(OnlineStore $store, string $code)
    {
        $url = "https://auth.lazada.com/rest/auth/token/create";
        $timestamp = round(microtime(true) * 1000);

        $params = [
            "app_key"    => $store->api_key,
            "timestamp"  => $timestamp,
            "sign_method" => "sha256",
            "grant_type" => "authorization_code",
            "code"       => $code,
        ];

        // Generate signature
        $sign = $this->generateSignature($params, $store->client_secret);

        $response = Http::asForm()->post($url, array_merge($params, [
            "sign" => $sign
        ]));

        $data = $response->json();

        if (!isset($data["access_token"])) {
            return response()->json([
                "success" => false,
                "message" => "Failed to obtain access token",
                "response" => $data
            ], 400);
        }

        // Save access token to database
        $store->update([
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_at'    => Carbon::now()->addSeconds($data['expires_in']),
        ]);

        return response()->json([
            "success" => true,
            "message" => "Access token retrieved successfully",
            "store"   => $store,
            "response" => $data
        ]);
    }

    /**
     * STEP 4:
     * Refresh the access token when it is expired.
     */
    public function refreshToken($storeId)
    {
        $store = OnlineStore::findOrFail($storeId);

        if (!$store->refresh_token) {
            return response()->json([
                'error' => 'No refresh token available'
            ], 400);
        }

        $url = "https://auth.lazada.com/rest/auth/token/refresh";
        $timestamp = round(microtime(true) * 1000);

        $params = [
            "app_key"       => $store->api_key,
            "refresh_token" => $store->refresh_token,
            "timestamp"     => $timestamp,
            "sign_method"   => "sha256",
        ];

        // Generate signature
        $sign = $this->generateSignature($params, $store->client_secret);

        $response = Http::asForm()->post($url, array_merge($params, [
            "sign" => $sign
        ]));

        $data = $response->json();

        if (!isset($data["access_token"])) {
            return response()->json([
                "success" => false,
                "message" => "Failed to refresh access token",
                "response" => $data
            ], 400);
        }

        // Save updated tokens
        $store->update([
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_at'    => Carbon::now()->addSeconds($data['expires_in']),
        ]);

        return response()->json([
            "success" => true,
            "message" => "Token refreshed successfully",
            "store"   => $store,
            "response" => $data
        ]);
    }

    /**
     * HELPER:
     * Lazada requires a signature generated from sorted parameters.
     * This creates the HMAC-SHA256 signature in the correct format.
     */
    private function generateSignature(array $params, string $secret)
    {
        // Lazada requires parameters to be sorted alphabetically
        ksort($params);

        // Build the base string (key + value)
        $baseString = "";
        foreach ($params as $key => $value) {
            $baseString .= $key . $value;
        }

        // Generate the signature
        return strtoupper(hash_hmac("sha256", $baseString, $secret));
    }
}
