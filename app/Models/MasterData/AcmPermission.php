<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AcmPermission extends Model
{
    use HasUuids;

    protected $table = 'acm_permissions';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'role_id',
        'menu_key',
        'can_create',
        'can_read',
        'can_update',
        'can_delete',
        'can_force_delete',
    ];

    protected $casts = [
        'can_create' => 'boolean',
        'can_read'   => 'boolean',
        'can_update' => 'boolean',
        'can_delete' => 'boolean',
        'can_force_delete' => 'boolean',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
