<?php

namespace App\Models\Transactions;

use App\Models\MasterData\Product;
use App\Models\Transactions\Order;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    /** @use HasFactory<\Database\Factories\Transactions\OrderItemFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'trx_order_items';

    protected $fillable = ['order_id', 'order_item_id', 'sku', 'product_id', 'item_prepared_at'];

    // Define relationship with Scanned Item model
    public function scanned_items()
    {
        return $this->hasMany(ScannedItem::class, 'order_item_id');
    }

    // Define relationship with Product model
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // Define relationship with Order model
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
