<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inventory extends Model
{
    /** @use HasFactory<\Database\Factories\MasterData\InventoryFactory> */
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'mdx_inventories';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'model_id',
        'color_id',
        'size_id'
    ];

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


    public function detail()
    {
        return $this->hasMany(InventoryDetail::class, 'inventory_id');
    }
}
