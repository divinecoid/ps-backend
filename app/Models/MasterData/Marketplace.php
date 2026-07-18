<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Marketplace extends Model
{
    /** @use HasFactory<\Database\Factories\MarketplaceFactory> */
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'mdx_marketplaces';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'alias',
        'base_api_url',
        'description',
        'is_need_checker'
    ];

    // Define relationship with Online Store model
    public function online_store()
    {
        return $this->hasMany(OnlineStore::class, 'marketplace_id');
    }
}
