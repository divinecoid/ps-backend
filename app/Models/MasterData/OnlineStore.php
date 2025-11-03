<?php

namespace App\Models\MasterData;

use App\Models\Transactions\Order;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OnlineStore extends Model
{
    /** @use HasFactory<\Database\Factories\OnlineStoreFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'mdx_online_stores';

    protected $fillable = [
        'marketplace_id',
        'store_code',
        'store_name',
        'api_key',
        'client_id',
        'client_secret',
        'store_url',
        'is_active'
    ];

    // Define relationship with Order model
    public function order()
    {
        return $this->hasMany(Order::class, 'online_store_id');
    }

    // Define relationship with Marketplace model
    public function marketplace()
    {
        return $this->belongsTo(Marketplace::class, 'marketplace_id');
    }
}
