<?php

namespace App\Models\Transactions;

use App\Models\MasterData\OnlineStore;
use App\Models\MasterData\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'trx_orders';

    protected $fillable = [
        'awb_code',
        'read_at',
        'prepared_at',
        'prepare_duration',
        'readytoship_at',
        'readytoship_marketplace',
        'online_store_id',
        'marketplace_id',
        'item_count',
        'unique_item_count',
        'status',
        'total_weight',
        'total_price',
        'total_shipping',
        'total_amount',
        'preparist_user_id',
        'customer_name',
        'customer_phone',
        'customer_address',
    ];

    // Define relationship with Order Item model
    public function order_items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    // Define relationship with Order Note model
    public function order_note()
    {
        return $this->hasMany(OrderNote::class, 'order_id');
    }

    // Define relationship with Online Store model
    public function online_store()
    {
        return $this->belongsTo(OnlineStore::class, 'online_store_id');
    }

    // Define relationship with User model
    public function user()
    {
        return $this->belongsTo(User::class, 'preparist_user_id');
    }
}
