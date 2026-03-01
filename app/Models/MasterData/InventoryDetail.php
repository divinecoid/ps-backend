<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryDetail extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'mdx_inventory_details';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'series',
        'quantity'
    ];
    public function inventory()
    {
        return $this->belongsTo(Inventory::class, 'inventory_id');
    }

}