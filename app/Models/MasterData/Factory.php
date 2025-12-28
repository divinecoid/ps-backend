<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Factory extends Model
{
    /** @use HasFactory<\Database\Factories\MasterData\FactoryFactory> */
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'mdx_factories';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
    ];
    
    // Define relationship with Inventory model
    public function inventory()
    {
        return $this->hasMany(Inventory::class, 'factory_id');
    }
}
