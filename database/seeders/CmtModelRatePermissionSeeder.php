<?php

namespace Database\Seeders;

use App\Models\MasterData\AcmPermission;
use App\Models\MasterData\Role;
use Illuminate\Database\Seeder;

/**
 * Grants the new `master_cmt_rate` ACM menu permission to every existing
 * role, mirroring whatever access that role already has on `master_cmt`
 * (same read/create/update/delete/force_delete flags). Safe to re-run.
 */
class CmtModelRatePermissionSeeder extends Seeder
{
    public function run(): void
    {
        Role::all()->each(function (Role $role) {
            $cmtPerm = AcmPermission::where('role_id', $role->id)
                ->where('menu_key', 'master_cmt')
                ->first();

            AcmPermission::updateOrCreate(
                ['role_id' => $role->id, 'menu_key' => 'master_cmt_rate'],
                [
                    'can_create' => $cmtPerm?->can_create ?? false,
                    'can_read' => $cmtPerm?->can_read ?? false,
                    'can_update' => $cmtPerm?->can_update ?? false,
                    'can_delete' => $cmtPerm?->can_delete ?? false,
                    'can_force_delete' => $cmtPerm?->can_force_delete ?? false,
                ]
            );
        });
    }
}
