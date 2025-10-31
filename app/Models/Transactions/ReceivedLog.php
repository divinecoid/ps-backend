<?php

namespace App\Models\Transactions;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReceivedLog extends Model
{
    /** @use HasFactory<\Database\Factories\Transactions\ReceivedLogFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'trx_received_logs';

    protected $fillable = ['request_id', 'qty', 'received_date'];

    // Define relationship with Requests model
    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }
}
