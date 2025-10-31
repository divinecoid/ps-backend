<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Size extends Model
{
    /** @use HasFactory<\Database\Factories\MasterData\SizeFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'mdx_sizes';

    protected $fillable = [
        'code',
        'name'
    ];

    // Define relationship with Product model
    public function product()
    {
        return $this->hasMany(Product::class, 'size_id');
    }
}
