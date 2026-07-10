<?php

namespace App\Models\Transactions;

use App\Models\MasterData\Marketplace;
use App\Models\MasterData\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManualOutbound extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'trx_manual_outbounds';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'marketplace_id',
        'user_id',
        'outbound_date',
        'notes',
    ];

    protected $casts = [
        'outbound_date' => 'datetime',
    ];

    public function marketplace()
    {
        return $this->belongsTo(Marketplace::class, 'marketplace_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function details()
    {
        return $this->hasMany(ManualOutboundDetail::class, 'manual_outbound_id');
    }
}
