<?php

namespace App\Models\Transactions;

// use App\Models\MasterData\Inventory;
use App\Models\MasterData\CMT;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Request extends Model
{
    /** @use HasFactory<\Database\Factories\Transactions\RequestFactory> */
    use HasFactory, HasUuids;

    protected $table = 'trx_requests';

    protected $fillable = ['cmt_id', 'status'];

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

    public function request_detail()
    {
        return $this->hasMany(RequestDetail::class, 'request_id');
    }

    public function cmt()
    {
        return $this->belongsTo(CMT::class, 'cmt_id');
    }

    public function isCompleted()
    {
        return !$this->request_detail()
            ->whereRaw('(rec_qty + rec_bs_qty) < req_qty')
            ->exists();
    }
}
