<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CMT extends Model
{
    /** @use HasFactory<\Database\Factories\MasterData\CMTFactory> */
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'mdx_cmts';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'contact_person',
        'phone',
        'address'
    ];

    // Define relationship with Inventory model
    public function inventory()
    {
        return $this->hasMany(Inventory::class, 'cmt_id');
    }
}
