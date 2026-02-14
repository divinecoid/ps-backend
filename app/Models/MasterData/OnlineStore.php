<?php

namespace App\Models\MasterData;

use App\Models\Transactions\Order;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class OnlineStore extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'mdx_online_stores';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'marketplace_id',
        'store_code',
        'store_name',
        'shop_id',
        'api_key',
        'client_id',
        'client_secret',
        'store_url',
        'is_active',

        // OAuth fields
        'redirect_uri',
        'auth_code',
        'access_token',
        'refresh_token',
        'access_token_expires_at',
        'refresh_token_expires_at',

        // Marketplace specific fields
        'shop_cipher',
    ];

    /**
     * Casts for automatic Carbon and boolean conversion.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'access_token_expires_at' => 'datetime',
        'refresh_token_expires_at' => 'datetime',
    ];

    /**
     * Define relationship with Order model.
     */
    public function order()
    {
        return $this->hasMany(Order::class, 'online_store_id');
    }

    /**
     * Define relationship with Marketplace model.
     */
    public function marketplace()
    {
        return $this->belongsTo(Marketplace::class, 'marketplace_id');
    }

    /**
     * Check if the access token is expired.
     */
    public function isTokenExpired(): bool
    {
        return !$this->access_token_expires_at || $this->access_token_expires_at->isPast();
    }

    /**
     * Automatically determine if store has valid OAuth credentials.
     */
    public function hasValidCredentials(): bool
    {
        return $this->api_key && $this->client_secret && $this->redirect_uri;
    }
}
