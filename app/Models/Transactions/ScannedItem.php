<?php

namespace App\Models\Transactions;

use App\Models\MasterData\Inventory;
use App\Models\MasterData\Rack;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScannedItem extends Model
{
    /** @use HasFactory<\Database\Factories\Transactions\ScannedItemFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'trx_scanned_items';

    protected $fillable = ['inventory_id', 'rack_id', 'barcode', 'order_item_id'];

    // Define relationship with Rack model
    public function rack()
    {
        return $this->belongsTo(Rack::class, 'rack_id');
    }

    // Define relationship with Inventory model
    public function inventory()
    {
        return $this->belongsTo(Inventory::class, 'inventory_id');
    }

    // Define relationship with Order Item model
    public function order_item()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
