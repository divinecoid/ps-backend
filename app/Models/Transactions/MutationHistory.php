<?php

namespace App\Models\Transactions;

use App\Models\MasterData\Rack;
use App\Models\MasterData\User;
use App\Models\MasterData\Warehouse;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MutationHistory extends Model
{
    use HasUuids;

    protected $table = 'trx_mutation_histories';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'receivedlog_id', 'barcode', 'model_id', 'color_id', 'size_id',
        'from_rack_id', 'to_rack_id', 'from_warehouse_id', 'to_warehouse_id', 'user_id',
    ];

    public function receivedlog() { return $this->belongsTo(Receivedlog::class, 'receivedlog_id'); }
    public function fromRack() { return $this->belongsTo(Rack::class, 'from_rack_id')->withTrashed(); }
    public function toRack() { return $this->belongsTo(Rack::class, 'to_rack_id')->withTrashed(); }
    public function fromWarehouse() { return $this->belongsTo(Warehouse::class, 'from_warehouse_id'); }
    public function toWarehouse() { return $this->belongsTo(Warehouse::class, 'to_warehouse_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
