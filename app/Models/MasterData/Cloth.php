<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cloth extends Model
{
    /** @use HasFactory<\Database\Factories\MasterData\ClothFactory> */
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'mdx_clothes';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'factory_id',
        'gram',
        'roll_size_id',
        'color_id',
        'quantity',
        'sequence'
    ];

    // Define relationship with Product model
    public function clothes()
    {
        return $this->belongsTo(RollSize::class, 'roll_size_id');
    }

    public function factory()
    {
        return $this->belongsTo(Factory::class, 'factory_id');
    }

    public function color()
    {
        return $this->belongsTo(Color::class, 'color_id');
    }
    
    public function roll_size()
    {
        return $this->belongsTo(RollSize::class, 'roll_size_id');
    }
}
