<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ConfigurationHistory extends Model
{
    use HasUuids;

    protected $table = 'mdx_configurations_histories';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'configuration_id',
        'old_value',
        'new_value',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function configuration()
    {
        return $this->belongsTo(Configuration::class, 'configuration_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
