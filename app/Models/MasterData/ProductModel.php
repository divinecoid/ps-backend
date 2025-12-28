<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductModel extends Model
{
    /** @use HasFactory<\Database\Factories\MasterData\ProductModelFactory> */
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'mdx_models';

    protected $fillable = [
        'sku',
        'name'
    ];

    // Define relationship with Product model
    public function product()
    {
        return $this->hasMany(Product::class, 'model_id');
    }
}
