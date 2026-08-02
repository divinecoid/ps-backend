<?php

namespace App\Models\Transactions;

// use App\Models\MasterData\Inventory;
// use App\Models\MasterData\CMT;
use App\Models\FabricCuttingFabric;
use App\Models\MasterData\Cloth;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FabricCutting extends Model
{
    /** @use HasFactory<\Database\Factories\Transactions\FabricCuttingFactory> */
    use HasFactory, HasUuids;

    protected $table = 'trx_fabric_cuttings';

    protected $fillable = [
        'status',
        'serial_number'
    ];

    public function fabric_cutting_request_detail()
    {
        return $this->hasMany(FabricCuttingDetail::class, 'fabric_cutting_id');
    }

    public function fabric_detail()
    {
        return $this->hasMany(FabricCuttingFabric::class, 'fabric_cutting_id');
    }

    public function clothes()
    {
        return $this->hasOneThrough(
            Cloth::class,
            FabricCuttingFabric::class,
            'fabric_cutting_id',
            'id',
            'id',
            'fabric_id'
        );
    }

    public function fabric_cutting_receives()
    {
        return $this->hasMany(FabricCuttingReceive::class, 'fabric_cutting_id');
    }

    public function isCompleted()
    {
        // To be updated when receive logic is complete
        return false;
    }
}
