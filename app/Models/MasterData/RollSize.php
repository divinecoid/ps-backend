<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RollSize extends Model
{
    /** @use HasFactory<\Database\Factories\MasterData\RollSizeFactory> */
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'mdx_roll_sizes';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'size'
    ];

    // Define relationship with Product model
    public function clothes()
    {
        return $this->hasMany(Cloth::class, 'roll_size_id');
    }
}
