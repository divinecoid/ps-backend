<?php

namespace App\Models\Transactions;

use App\Models\MasterData\Rack;
use App\Models\MasterData\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockOpname extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'trx_stock_opnames';

    protected $fillable = ['rack_id', 'user_id', 'notes', 'system_qty', 'counted_qty'];

    public function rack()
    {
        return $this->belongsTo(Rack::class, 'rack_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items()
    {
        return $this->hasMany(StockOpnameItem::class, 'stock_opname_id');
    }

    public function barcodes()
    {
        return $this->hasMany(StockOpnameBarcode::class, 'stock_opname_id');
    }
}
