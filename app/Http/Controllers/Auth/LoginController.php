<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiFilterTrait;
use App\Models\Auth\RefreshToken;
use App\Models\MasterData\User;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Support\AuditLogger;


class LoginController extends Controller
{
    use ApiFilterTrait;
    public function login(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string'
            ]);

            $user = User::where('username', $request->username)->first();
            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid username or password'
                ], 401);
            }

            //clear expired token
            $user->refreshTokens()->where('expires_at', '<', now())->delete();

            $activeTokens = $user->refreshTokens()->where('revoked', false)->count();
            if ($activeTokens >= $this->getMaxTokens()) {
                $oldestToken = $user->refreshTokens()->where('revoked', false)->oldest()->first();
                if ($oldestToken) {
                    $oldestToken->delete();
                }
            }

            $accessToken = JWTAuth::fromUser($user);

            $refreshToken = RefreshToken::createToken($user);

            AuditLogger::log('auth', 'LOGIN', "Pengguna {$user->username} berhasil masuk", $user->id);

            // $payload = JWTAuth::setToken($accessToken)->getPayload();
            // Log::info('JWT Payload:', $payload->toArray());

            return response()->json([
                'success' => true,
                'message' => "OK",
                'token' => $accessToken,
                'refresh_token' => $refreshToken->token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                ]
            ]);

        } catch (ConnectionException $e) {
            Log::error('Database connection error' . $e->getMessage());
            return response()->json([
                'status' => false,
                'code' => 503,
            ], 503);
        } catch (QueryException $e) {
            Log::error('Database query error' . $e->getMessage());
            return response()->json([
                'status' => false,
                'code' => 500,
            ], 500);
        } catch (Exception $e) {
            Log::error('Unexpected error' . $e->getMessage());
            return response()->json([
                'status' => false,
                'code' => 500,
            ], 500);
        }
    }

    public function refresh(Request $request)
    {
        try {

            $request->validate([
                'refresh_token' => 'required|string'
            ]);

            $refreshToken = RefreshToken::findByToken($request->refresh_token);

            if (!$refreshToken || !$refreshToken->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => "Invalid refresh token"
                ], 401);
            }

            $user = $refreshToken->user;

            $accessToken = JWTAuth::fromUser($user);

            return response()->json([
                'success' => true,
                'message' => "OK",
                'token' => $accessToken,
                'token_type' => 'Bearer'
            ]);

        } catch (ConnectionException $e) {
            Log::error('Database connection error' . $e->getMessage());
            return response()->json([
                'status' => false,
                'code' => 503,
            ], 503);
        } catch (QueryException $e) {
            Log::error('Database query error' . $e->getMessage());
            return response()->json([
                'status' => false,
                'code' => 500,
            ], 500);
        } catch (Exception $e) {
            Log::error('Unexpected error' . $e->getMessage());
            return response()->json([
                'status' => false,
                'code' => 500,
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $request->validate([
                'refresh_token' => 'required|string'
            ]);

            $refreshToken = RefreshToken::findByToken($request->refresh_token);

            if (!$refreshToken || !$refreshToken->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => "Invalid refresh token"
                ], 401);
            }

            $user = $refreshToken->user;
            if ($user) {
                AuditLogger::log('auth', 'LOGOUT', "Pengguna {$user->username} keluar", $user->id);
            }

            $refreshToken->revoke();

            return response()->json([
                'success' => true,
                'message' => "OK",
            ]);

        } catch (ConnectionException $e) {
            Log::error('Database connection error' . $e->getMessage());
            return response()->json([
                'status' => false,
                'code' => 503,
            ], 503);
        } catch (QueryException $e) {
            Log::error('Database query error' . $e->getMessage());
            return response()->json([
                'status' => false,
                'code' => 500,
            ], 500);
        } catch (Exception $e) {
            Log::error('Unexpected error' . $e->getMessage());
            return response()->json([
                'status' => false,
                'code' => 500,
            ], 500);
        }
    }
}