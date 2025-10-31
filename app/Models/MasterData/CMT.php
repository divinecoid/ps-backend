<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CMT extends Model
{
    /** @use HasFactory<\Database\Factories\MasterData\CMTFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'mdx_cmts';

    protected $fillable = [
        'code',
        'name',
    ];

    // Define relationship with Inventory model
    public function inventory()
    {
        return $this->hasMany(Inventory::class, 'cmt_id');
    }
}
