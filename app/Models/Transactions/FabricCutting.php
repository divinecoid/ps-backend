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
        'serial_number',
    ];

    // Define relationship with Received Log model
    // public function recevied_log()
    // {
    //     return $this->hasMany(ReceivedLog::class, 'request_id');
    // }

    // // Define relationship with Inventory model
    // public function inventory()
    // {
    //     return $this->belongsTo(Inventory::class, 'inventory_id');
    // }


    // public function receive_log()
    // {
    //     return $this->hasMany(Receivedlog::class, 'cmt_id', 'cmt_id');
    // }
    public function fabric_cutting_request_detail()
    {
        return $this->hasMany(FabricCuttingDetail::class, 'fabric_cutting_id');
    }

    public function fabric_detail()
    {
        return $this->hasMany(FabricCuttingFabric::class, 'fabric_cutting_id');
    }

    public function isCompleted()
    {
        return !$this->fabric_cutting_request_detail()
            ->whereRaw('avl_qty < req_qty')
            ->exists();
    }
}
