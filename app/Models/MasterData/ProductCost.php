<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCost extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'mdx_product_costs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'product_id',
        'fabric_cost',
        'shipping_cost',
        'cmt_fee',
        'total_hpp',
        'is_estimated',
        'calculated_at',
    ];

    protected $casts = [
        'is_estimated' => 'boolean',
        'calculated_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
