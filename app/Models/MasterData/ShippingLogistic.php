<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingLogistic extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'mdx_shipping_logistics';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'marketplace_id',
        'logistic_name',
        'logistic_id',
        'logistic_type',
        'is_active'
    ];

    public function marketplace()
    {
        return $this->belongsTo(Marketplace::class, 'marketplace_id');
    }
}
