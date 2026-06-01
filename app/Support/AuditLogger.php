<?php

namespace App\Support;

use App\Models\MasterData\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Write an audit log entry.
     */
    public static function log(string $module, string $action, string $details = null, ?string $userId = null): void
    {
        try {
            AuditLog::create([
                'user_id'    => $userId ?? Auth::id(),
                'module'     => $module,
                'action'     => $action,
                'details'    => $details,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            logger()->error("Failed to write audit log: " . $e->getMessage());
        }
    }

    /**
     * Map Eloquent model classes to ACM menu keys.
     */
    public static function mapModelToModule(string $modelClass): string
    {
        $map = [
            \App\Models\MasterData\Color::class         => 'master_warna',
            \App\Models\MasterData\CMT::class           => 'master_cmt',
            \App\Models\MasterData\Cloth::class         => 'gudang_kain',
            \App\Models\MasterData\Configuration::class => 'master_konfigurasi',
            \App\Models\MasterData\Factory::class       => 'master_pabrik',
            \App\Models\MasterData\Inventory::class     => 'gudang_besar',
            \App\Models\MasterData\Marketplace::class   => 'master_marketplace',
            \App\Models\MasterData\OnlineStore::class   => 'master_toko',
            \App\Models\MasterData\Product::class       => 'master_product',
            \App\Models\MasterData\ProductModel::class => 'master_model',
            \App\Models\MasterData\Rack::class          => 'master_rak',
            \App\Models\MasterData\Role::class          => 'role',
            \App\Models\MasterData\RollSize::class      => 'master_roll_size',
            \App\Models\MasterData\Sequence::class      => 'sequence',
            \App\Models\MasterData\Size::class          => 'master_ukuran',
            \App\Models\MasterData\User::class          => 'user',
            \App\Models\MasterData\Warehouse::class     => 'master_gudang',
        ];

        return $map[$modelClass] ?? 'unknown';
    }
}
