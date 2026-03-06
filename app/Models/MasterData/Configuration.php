<?php

namespace App\Models\MasterData;

use App\Enums\ConfigDataType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Configuration extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'mdx_configurations';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'config_key',
        'config_value',
        'data_type',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'data_type' => ConfigDataType::class,
    ];

    /**
     * Get the config_value cast to the appropriate PHP type based on data_type.
     */
    public function getTypedValue()
    {
        return match ($this->data_type) {
            ConfigDataType::BOOLEAN => filter_var($this->config_value, FILTER_VALIDATE_BOOLEAN),
            ConfigDataType::INTEGER => (int) $this->config_value,
            ConfigDataType::DECIMAL => (float) $this->config_value,
            default => $this->config_value,
        };
    }

    public function histories()
    {
        return $this->hasMany(ConfigurationHistory::class, 'configuration_id')->orderByDesc('changed_at');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
