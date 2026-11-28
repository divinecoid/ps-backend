<?php

namespace App\Models\Transactions;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StockOpnameItem extends Model
{
    use HasUuids;

    protected $table = 'trx_stock_opname_items';

    public $timestamps = false;

    protected $fillable = ['model', 'color', 'size', 'system_qty', 'counted_qty', 'is_manual'];

    protected $casts = ['is_manual' => 'boolean'];
}
