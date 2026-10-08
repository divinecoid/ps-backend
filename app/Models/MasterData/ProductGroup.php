<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductGroup extends Model
{
    use SoftDeletes, HasUuids;

    protected $table = 'mdx_product_groups';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'model_id',
        'color_id',
        'size_id',
        'series',
        'qty',
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

    // Not a real FK relation: mdx_products has no product_group_id column by design
    // (the existing Product table/generation flow is intentionally left untouched).
    // Individual product rows are matched by their shared model/color/size/series.
    public function products()
    {
        $query = Product::query()
            ->where('model_id', $this->model_id)
            ->where('series', $this->series);

        $this->color_id ? $query->where('color_id', $this->color_id) : $query->whereNull('color_id');
        $this->size_id ? $query->where('size_id', $this->size_id) : $query->whereNull('size_id');

        return $query;
    }
}
