<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sequence extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'mdx_sequences';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'color_id',
        'key',
        'format',
        'current',
        'limit',
    ];

    public function color()
    {
        return $this->belongsTo(Color::class, 'color_id');
    }
}
