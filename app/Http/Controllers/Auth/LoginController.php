<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Hash;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Log;
use Request;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string'
            ]);

            $user = User::where('name', $request->username)->first();
            if (!$user || Hash::check($request->password, $user->password)) {
                return response()-> json([
                    'success' => false,
                    'message' => 'Invalid username or password'
                ], 401);
            }
            
            
            //clear expired token
            // $user->ref





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