<?php

namespace App\Models\Transactions;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnReceiptDetail extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'trx_return_receipt_details';

    protected $fillable = [
        'return_receipt_id',
        'order_item_id',
        'barcode_scanned',
        'is_received',
    ];

    protected $casts = [
        'is_received' => 'boolean',
    ];

    public function returnReceipt()
    {
        return $this->belongsTo(ReturnReceipt::class, 'return_receipt_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
