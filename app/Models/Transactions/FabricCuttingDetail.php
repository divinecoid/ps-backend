<?php

namespace App\Models\Transactions;

use App\Models\MasterData\ProductModel;
use App\Models\MasterData\Size;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FabricCuttingDetail extends Model
{
    /** @use HasFactory<\Database\Factories\Transactions\FabricCuttingDetailFactory> */
    use HasFactory, HasUuids;

    protected $table = 'trx_fabric_cutting_details';

    protected $fillable = [
        'fabric_cutting_id',
        'model_id',
        'size_id',
        'req_qty',
        'rec_qty',
        'barcode'
    ];

    public function fabric_cutting_request()
    {
        return $this->belongsTo(FabricCutting::class, 'fabric_cutting_id');
    }

    public function model()
    {
        return $this->belongsTo(ProductModel::class, 'model_id');
    }

    public function size()
    {
        return $this->belongsTo(Size::class, 'size_id');
    }

    // public function receivedlog_detail ()
    // {
    //     return $this->hasMany(ReceivedlogDetail::class, 'request_detail_id');
    // }

}
