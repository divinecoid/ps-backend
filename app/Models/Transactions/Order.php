<?php

namespace App\Models\Transactions;

use App\Models\MasterData\OnlineStore;
use App\Models\MasterData\ShippingLogistic;
use App\Models\MasterData\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'trx_orders';

    protected $fillable = [
        'order_sn',
        'awb_code',
        'read_at',
        'prepared_at',
        'prepare_duration',
        'readytoship_at',
        'readytoship_marketplace',
        'online_store_id',
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
        'marketplace_id',
        'shipping_logistic_id',
        'is_need_checker',
        'checked_by',
        'is_approved',
        'is_outbounded',
    ];

    protected $casts = [
        'status' => \App\Enums\OrderStatus::class,
        'read_at' => 'datetime',
        'prepared_at' => 'datetime',
        'readytoship_at' => 'datetime',
        'is_outbounded' => 'boolean',
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

    // Define relationship with checker
    public function checkedBy()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    // Define relationship with Marketplace model
    public function marketplace()
    {
        return $this->belongsTo(\App\Models\MasterData\Marketplace::class, 'marketplace_id');
    }

    // Define relationship with ShippingLogistic model
    public function shippingLogistic()
    {
        return $this->belongsTo(ShippingLogistic::class, 'shipping_logistic_id');
    }
}
