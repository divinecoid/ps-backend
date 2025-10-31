<?php

namespace App\Models\Transactions;

use App\Models\MasterData\Inventory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Request extends Model
{
    /** @use HasFactory<\Database\Factories\Transactions\RequestFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'trx_requests';

    protected $fillable = ['inventory_id', 'format', 'start', 'end', 'retrieved_qty', 'request_date'];

    // Define relationship with Received Log model
    public function recevied_log()
    {
        return $this->hasMany(ReceivedLog::class, 'request_id');
    }

    // Define relationship with Inventory model
    public function inventory()
    {
        return $this->belongsTo(Inventory::class, 'inventory_id');
    }
}
