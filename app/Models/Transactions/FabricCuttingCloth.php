<?php

namespace App\Models\Transactions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FabricCuttingCloth extends Model
{
    use HasFactory, \Illuminate\Database\Eloquent\Concerns\HasUuids;

    protected $table = 'trx_fabric_cutting_clothes';

    protected $fillable = [
        'fabric_cutting_id',
        'fabric_id',
        'quantity',
    ];

    public function fabric_cutting()
    {
        return $this->belongsTo(FabricCutting::class, 'fabric_cutting_id');
    }

    public function fabric()
    {
        return $this->belongsTo(\App\Models\MasterData\Cloth::class, 'fabric_id');
    }
}
