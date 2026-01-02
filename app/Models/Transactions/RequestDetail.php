<?php

namespace App\Models\Transactions;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestDetail extends Model
{
    /** @use HasFactory<\Database\Factories\Transactions\RequestDetailFactory> */
    use HasFactory, HasUuids;

    protected $table = 'trx_request_details';

    protected $fillable = ['request_id', 'model_id', 'req_dozen_qty', 'req_piece_qty', 'rec_dozen_qty', 'rec_piece_qty', 'rec_bs_qty', 'barcode'];

    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }

    public function model()
    {
        return $this->hasMany(Model::class, 'request_id');
    }

}
