<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Color extends Model
{
    /** @use HasFactory<\Database\Factories\MasterData\ColorFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'mdx_colors';

    protected $fillable = [
        'code',
        'name'
    ];

    // Define relationship with Product model
    public function product()
    {
        return $this->hasMany(Product::class, 'color_id');
    }
}
