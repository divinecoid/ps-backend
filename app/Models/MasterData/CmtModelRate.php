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
        'group_id',
        'kategori',
        'rate',
    ];

    // Define relationship with CmtRateGroup model
    public function group()
    {
        return $this->belongsTo(CmtRateGroup::class, 'group_id');
    }
}
