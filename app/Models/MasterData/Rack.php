<?php

namespace App\Models\MasterData;

use App\Models\Transactions\ScannedItem;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rack extends Model
{
    /** @use HasFactory<\Database\Factories\MasterData\RackFactory> */
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'mdx_racks';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['code', 'name', 'warehouse_id', 'model_id'];

    // Define relationship with Warehouse model
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    // Define relationship with ProductModel (many racks -> 1 model)
    public function model()
    {
        return $this->belongsTo(ProductModel::class, 'model_id');
    }

    // Define relationship with Scanned Item model
    public function scanned_item()
    {
        return $this->hasMany(ScannedItem::class, 'rack_id');
    }

    // Define relationship with Inventory model
    public function inventories()
    {
        return $this->hasMany(Inventory::class, 'rack_id');
    }

    public function product()
    {
        return $this->hasMany(Product::class, 'rack_id');
    }
}
