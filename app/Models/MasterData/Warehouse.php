<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    /** @use HasFactory<\Database\Factories\MasterData\WarehouseFactory> */
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'mdx_warehouses';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['code', 'name', 'priority'];

    // Define relationship with Rack model
    public function rack()
    {
        return $this->hasMany(Rack::class, 'warehouse_id');
    }
}
