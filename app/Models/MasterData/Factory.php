<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Factory extends Model
{
    /** @use HasFactory<\Database\Factories\MasterData\FactoryFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'mdx_factories';

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
