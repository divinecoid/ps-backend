<?php

namespace App\Models\Transactions;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StockOpnameBarcode extends Model
{
    use HasUuids;

    protected $table = 'trx_stock_opname_barcodes';

    public $timestamps = false;

    protected $fillable = ['barcode', 'result', 'note'];
}
