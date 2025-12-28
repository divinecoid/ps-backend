<?php

namespace App\Models\MasterData;

use App\Models\Transactions\Request;
use App\Models\Transactions\ScannedItem;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inventory extends Model
{
    /** @use HasFactory<\Database\Factories\MasterData\InventoryFactory> */
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'mdx_inventories';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'serial_number',
        'product_id',
        'factory_id',
        'quantity',
        'cmt_id',
        'rack_id',
        'barcode_group'
    ];

    // Define relationship with Rack model
    public function rack()
    {
        return $this->belongsTo(Rack::class, 'rack_id');
    }

    // Define relationship with Product model
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // Define relationship with Factory model
    public function factory()
    {
        return $this->belongsTo(Factory::class, 'factory_id');
    }

    // Define relationship with CMT model
    public function cmt()
    {
        return $this->belongsTo(CMT::class, 'cmt_id');
    }

    // Define relationship with Scanned Item model
    public function scanned_item()
    {
        return $this->hasMany(ScannedItem::class, 'inventory_id');
    }

    // Define relationship with Request model
    public function request()
    {
        return $this->hasMany(Request::class, 'inventory_id');
    }
}
