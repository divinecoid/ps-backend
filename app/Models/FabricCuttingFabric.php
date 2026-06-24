<?php

namespace App\Models;

use App\Models\MasterData\Cloth;
use App\Models\Transactions\FabricCutting;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FabricCuttingFabric extends Model
{
    use HasUuids;

    protected $table = 'trx_fabric_cutting_fabrics';

    protected $fillable = [
        'fabric_cutting_id',
        'fabric_id',
        'quantity',
    ];

    public function fabricCutting()
    {
        return $this->belongsTo(FabricCutting::class, 'fabric_cutting_id');
    }

    public function cloth()
    {
        return $this->belongsTo(Cloth::class, 'fabric_id');
    }
}