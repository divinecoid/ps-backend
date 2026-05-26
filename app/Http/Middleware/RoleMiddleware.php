<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, $roles = null): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                    'data' => null
                ], 401);
            }

            if ($roles) {
                $roleArray = explode(',', $roles);
                $hasRole = false;
                foreach ($roleArray as $role) {
                    if ($user->hasRole(trim($role))) {
                        $hasRole = true;
                        break;
                    }
                }
                if (!$hasRole) {
                    $userRoles = $user->roles()->pluck('name')->implode(', ');

                    $userRoles = $userRoles ?: 'No role';

                    return response()->json([
                        'success' => false,
                        'message' => 'Role does not meet requirement, required: ' . $roles . ', provided: ' . $userRoles,
                        'data' => null
                    ], 403);
                }
            }


            $request->setUserResolver(function () use ($user) {
                return $user;
            });
            return $next($request);

        } catch (TokenExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token expired',
                'data' => null
            ], 401);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token',
                'data' => null
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No token',
                'data' => null
            ], 401);
        } catch (Exception $e) {
            Log::error($e);
            return response()->json([
                'success' => false,
                'message' => 'Unknown error',
                'data' => null
            ], 401);
        }
    }
}