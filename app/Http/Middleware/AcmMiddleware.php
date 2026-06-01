<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\MasterData\AcmPermission;
use App\Models\MasterData\Role;

class AcmMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Usage in routes: middleware('acm:menu_key,action')
     * action: create | read | update | delete
     *
     * Admin always bypasses ACM checks.
     */
    public function handle(Request $request, Closure $next, string $menuKey, string $action): Response
    {
        try {
            // The user is already resolved by RoleMiddleware running before this.
            // We resolve it again here for safety.
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                    'data'    => null,
                ], 401);
            }

            // Admin always bypasses ACM
            if ($user->hasRole('admin')) {
                return $next($request);
            }

            // Map action string to DB column
            $columnMap = [
                'create'       => 'can_create',
                'read'         => 'can_read',
                'update'       => 'can_update',
                'delete'       => 'can_delete',
                'force_delete' => 'can_force_delete',
            ];

            $column = $columnMap[$action] ?? null;
            if (!$column) {
                return response()->json([
                    'success' => false,
                    'message' => "ACM action '{$action}' tidak dikenal",
                    'data'    => null,
                ], 400);
            }

            // Get all roles of the current user
            $userRoles = $user->roles()->pluck('id')->toArray();

            if (empty($userRoles)) {
                return $this->forbidden($menuKey, $action);
            }

            // Check if ANY of the user's roles grants the required permission
            $hasPermission = AcmPermission::where('menu_key', $menuKey)
                ->whereIn('role_id', $userRoles)
                ->where($column, true)
                ->exists();

            if (!$hasPermission) {
                return $this->forbidden($menuKey, $action);
            }

            return $next($request);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ACM check failed: ' . $e->getMessage(),
                'data'    => null,
            ], 401);
        }
    }

    private function forbidden(string $menuKey, string $action): Response
    {
        return response()->json([
            'success' => false,
            'message' => "Akses ditolak: Anda tidak memiliki izin '{$action}' untuk menu '{$menuKey}'",
            'data'    => null,
        ], 403);
    }
}
