<?php

namespace App\Models\Transactions;

use App\Models\MasterData\Cloth;
use App\Models\MasterData\ProductModel;
use App\Models\MasterData\Size;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestDetail extends Model
{
    /** @use HasFactory<\Database\Factories\Transactions\RequestDetailFactory> */
    use HasFactory, HasUuids;

    protected $table = 'trx_request_details';

    protected $fillable = [
        'request_id',
        'model_id',
        'cloth_id',
        'size_id',
        'req_qty',
        'rec_qty',
        'rec_bs_qty',
        'barcode'
    ];

    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }

    public function model()
    {
        return $this->belongsTo(ProductModel::class, 'model_id');
    }
    public function cloth()
    {
        return $this->belongsTo(Cloth::class, 'cloth_id');
    }
    public function size()
    {
        return $this->belongsTo(Size::class, 'size_id');
    }

    public function receivedlog_detail ()
    {
        return $this->hasMany(ReceivedlogDetail::class, 'request_detail_id');
    }

}
