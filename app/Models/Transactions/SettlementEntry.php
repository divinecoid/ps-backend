<?php

namespace App\Models\Transactions;

use App\Models\MasterData\OnlineStore;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SettlementEntry extends Model
{
    use HasUuids;

    protected $table = 'trx_settlement_entries';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'order_date' => 'date:Y-m-d',
        'settlement_date' => 'date:Y-m-d',
        'flags' => 'array',
        'fee_breakdown' => 'array',
        'raw_payload' => 'array',
    ];

    public function online_store()
    {
        return $this->belongsTo(OnlineStore::class, 'online_store_id');
    }

    public function import()
    {
        return $this->belongsTo(SettlementImport::class, 'import_id');
    }
}
