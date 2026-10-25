<?php

namespace App\Models\Transactions;

use App\Models\MasterData\OnlineStore;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SettlementImport extends Model
{
    use HasUuids;

    protected $table = 'trx_settlement_imports';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'online_store_id',
        'marketplace_id',
        'file_type',
        'file_name',
        'file_path',
        'file_hash',
        'status',
        'row_count',
        'imported_count',
        'duplicate_count',
        'error_count',
        'errors',
        'unmapped_columns',
        'user_id',
        'processed_at',
    ];

    protected $casts = [
        'errors' => 'array',
        'unmapped_columns' => 'array',
        'processed_at' => 'datetime',
    ];

    public function online_store()
    {
        return $this->belongsTo(OnlineStore::class, 'online_store_id');
    }

    public function entries()
    {
        return $this->hasMany(SettlementEntry::class, 'import_id');
    }
}
