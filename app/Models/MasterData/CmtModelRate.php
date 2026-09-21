<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmtModelRate extends Model
{
    use SoftDeletes, HasUuids;

    protected $table = 'mdx_cmt_model_rates';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'model_id',
        'kategori',
        'rate',
    ];

    // Define relationship with ProductModel model
    public function model()
    {
        return $this->belongsTo(ProductModel::class, 'model_id');
    }
}
