<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterData\OnlineStore;
use Illuminate\Http\Request;
use LazopClient;
use LazopRequest;
use Carbon\Carbon;
use Log;

class LazadaAuthController extends Controller
{
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
        ]);

        return redirect($authUrl);
    }

    /**
     * STEP 2
     * Callback → dapat CODE → Tukar dengan Access Token via SDK
     */
    public function handleCallback(Request $request, $id)
    {
        $store = OnlineStore::findOrFail($id);

        $code = $request->query("code");
        if (!$code) {
            return response()->json([
                "success" => false,
                "message" => "Authorization code not found in callback",
                "query"   => $request->query(),
            ], 400);
        }

        // SDK CLIENT
        $client = new LazopClient(
            "https://auth.lazada.com/rest",
            $store->api_key,
            $store->client_secret
        );

        // Lazada Token Request
        $req = new LazopRequest('/auth/token/create');
        $req->addApiParam("code", $code);

        // Execute Request
        $response = json_decode($client->execute($req), true);

        if (!isset($response["access_token"])) {
            return response()->json([
                "success" => false,
                "message" => "Failed to obtain access token",
                "response" => $response
            ], 400);
        }

        // Save token
        $store->update([
            "access_token"  => $response["access_token"],
            "refresh_token" => $response["refresh_token"] ?? null,
            "expires_at"    => Carbon::now()->addSeconds($response["expires_in"]),
        ]);

        // RETURN SIMPLE HTML PAGE
        return response("
            <html>
                <head>
                    <title>Authorization Success</title>
                    <meta name='password-manager' content='disable'>
                    <meta name='autofill' content='false'>
                    <meta name='google' content='notranslate'>
                    <style>
                        body {
                            font-family: Arial, sans-serif; 
                            background: #f5f5f5; 
                            padding: 40px; 
                            text-align: center;
                        }
                        .box {
                            background: white;
                            padding: 30px;
                            border-radius: 10px;
                            box-shadow: 0 0 10px rgba(0,0,0,0.1);
                            display: inline-block;
                        }
                        button {
                            margin-top: 20px;
                            padding: 10px 20px;
                            border: none;
                            background: #3498db;
                            color: white;
                            border-radius: 5px;
                            font-size: 14px;
                            cursor: pointer;
                        }
                        button:hover {
                            background: #2980b9;
                        }
                    </style>
                </head>
                <body>
                    <div class='box'>
                        <h2>Sukses!</h2>
                        <p>Silakan menutup halaman ini dan cek data terupdate pada online store tersebut.</p>
                    </div>
                </body>
            </html>
            ", 200)
            ->header('Content-Type', 'text/html')
            ->header("Cache-Control", "no-store");
    }
}
