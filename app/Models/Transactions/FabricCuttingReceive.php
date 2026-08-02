<?php

namespace App\Models\Transactions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FabricCuttingReceive extends Model
{
    use HasFactory, \Illuminate\Database\Eloquent\Concerns\HasUuids;

    protected $table = 'trx_fabric_cutting_receives';

    protected $fillable = [
        'fabric_cutting_id',
        'model_id',
        'cloth_id',
        'size_id',
        'qty',
    ];

    public function fabric_cutting()
    {
        return $this->belongsTo(FabricCutting::class, 'fabric_cutting_id');
    }

    public function model()
    {
        return $this->belongsTo(\App\Models\MasterData\ProductModel::class, 'model_id');
    }

    public function cloth()
    {
        return $this->belongsTo(\App\Models\MasterData\Cloth::class, 'cloth_id');
    }

    public function size()
    {
        return $this->belongsTo(\App\Models\MasterData\Size::class, 'size_id');
    }
}
