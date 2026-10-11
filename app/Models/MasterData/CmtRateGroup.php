<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmtRateGroup extends Model
{
    use SoftDeletes, HasUuids;

    protected $table = 'mdx_cmt_rate_groups';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
    ];

    // Garment models that belong to this payroll rate group (e.g. "Pendek", "Panjang", "Saku")
    public function models()
    {
        return $this->hasMany(ProductModel::class, 'cmt_rate_group_id');
    }

    public function rates()
    {
        return $this->hasMany(CmtModelRate::class, 'group_id');
    }
}
