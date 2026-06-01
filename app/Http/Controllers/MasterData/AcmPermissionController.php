<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\AcmPermission;
use App\Models\MasterData\Role;
use App\Support\AcmMenuRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcmPermissionController extends Controller
{
    /**
     * Get all ACM permissions for a specific role.
     * Merges existing DB entries with defaults for menus not yet configured.
     */
    public function index($roleId)
    {
        $role = Role::find($roleId);
        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role tidak ditemukan',
                'data'    => null,
            ], 404);
        }

        $allMenus    = AcmMenuRegistry::all();
        $existing    = AcmPermission::where('role_id', $roleId)
            ->get()
            ->keyBy('menu_key');

        $result = [];
        foreach ($allMenus as $key => $meta) {
            $perm = $existing->get($key);
            $result[] = [
                'menu_key'   => $key,
                'label'      => $meta['label'],
                'can_create' => $perm ? (bool) $perm->can_create : false,
                'can_read'   => $perm ? (bool) $perm->can_read   : false,
                'can_update' => $perm ? (bool) $perm->can_update  : false,
                'can_delete' => $perm ? (bool) $perm->can_delete  : false,
                'can_force_delete' => $perm ? (bool) $perm->can_force_delete : false,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'ACM permissions retrieved',
            'data'    => [
                'role' => [
                    'id'   => $role->id,
                    'name' => $role->name,
                ],
                'permissions' => $result,
            ],
        ]);
    }

    /**
     * Bulk upsert ACM permissions for a role.
     * Expects: { permissions: [{ menu_key, can_create, can_read, can_update, can_delete }] }
     */
    public function upsert(Request $request, $roleId)
    {
        $role = Role::find($roleId);
        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role tidak ditemukan',
                'data'    => null,
            ], 404);
        }

        $request->validate([
            'permissions'             => 'required|array',
            'permissions.*.menu_key'  => 'required|string',
            'permissions.*.can_create'=> 'required|boolean',
            'permissions.*.can_read'  => 'required|boolean',
            'permissions.*.can_update'=> 'required|boolean',
            'permissions.*.can_delete'=> 'required|boolean',
            'permissions.*.can_force_delete'=> 'required|boolean',
        ]);

        $validKeys = AcmMenuRegistry::keys();

        DB::transaction(function () use ($request, $roleId, $validKeys) {
            foreach ($request->permissions as $item) {
                $menuKey = $item['menu_key'];
                if (!in_array($menuKey, $validKeys)) {
                    continue; // skip unknown keys
                }

                AcmPermission::updateOrCreate(
                    ['role_id' => $roleId, 'menu_key' => $menuKey],
                    [
                        'can_create' => (bool) $item['can_create'],
                        'can_read'   => (bool) $item['can_read'],
                        'can_update' => (bool) $item['can_update'],
                        'can_delete' => (bool) $item['can_delete'],
                        'can_force_delete' => (bool) $item['can_force_delete'],
                    ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'ACM permissions berhasil disimpan',
            'data'    => null,
        ]);
    }
}
