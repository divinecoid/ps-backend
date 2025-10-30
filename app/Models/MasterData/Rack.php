<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rack extends Model
{
    /** @use HasFactory<\Database\Factories\MasterData\RackFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'mdx_racks';

    protected $fillable = ['code', 'name', 'warehouse_id'];

    // Define relationship with Warehouse model
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
