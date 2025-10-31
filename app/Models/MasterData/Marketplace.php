<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Marketplace extends Model
{
    /** @use HasFactory<\Database\Factories\MarketplaceFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'mdx_marketplaces';

    protected $fillable = [
        'code',
        'name',
        'base_api_url',
        'description',
        'is_needed_checker'
    ];

    // Define relationship with Online Store model
    public function online_store()
    {
        return $this->hasMany(OnlineStore::class, 'marketplace_id');
    }
}
