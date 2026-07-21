<?php

namespace App\Models\Transactions;

use App\Models\MasterData\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReturnReceipt extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'trx_return_receipts';

    protected $fillable = [
        'order_id',
        'awb_code',
        'received_at',
        'return_status',
        'received_by',
        'notes',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function details()
    {
        return $this->hasMany(ReturnReceiptDetail::class, 'return_receipt_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
