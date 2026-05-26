<?php

namespace App\Models\MasterData;

use App\Models\Transactions\OrderItem;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\MasterData\ProductFactory> */
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'mdx_products';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'model_id',
        'rack_id',
        'color_id',
        'size_id',
        'series',
        'barcode',
    ];

    // Define relationship with Order Item model
    public function order_item()
    {
        return $this->hasMany(OrderItem::class, 'product_id');
    }

    // Define relationship with Product Model model
    public function model()
    {
        return $this->belongsTo(ProductModel::class, 'model_id');
    }
    public function color()
    {
        return $this->belongsTo(Color::class, 'color_id');
    }
    public function size()
    {
        return $this->belongsTo(Size::class, 'size_id');
    }

    public function rack()
    {
        return $this->belongsTo(Rack::class, 'rack_id');
    }
}
