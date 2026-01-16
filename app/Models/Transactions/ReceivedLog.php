<?php

namespace App\Models\Transactions;

use App\Models\MasterData\Warehouse;
use App\Models\MasterData\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receivedlog extends Model
{
    /** @use HasFactory<\Database\Factories\Transactions\ReceivedlogFactory> */
    use HasFactory, HasUuids;

    protected $table = 'trx_receivedlogs';

    protected $fillable = [
        'request_id',
        'warehouse_id',
        'user_id',
        'received_date',
        'notes'
    ];

    protected $casts = [
        'received_date' => 'datetime',
    ];

    // Relationships
    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function details()
    {
        return $this->hasMany(ReceivedlogDetail::class, 'receivedlog_id');
    }
}
