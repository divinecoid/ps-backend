<?php

namespace App\Models\Transactions;

use App\Models\MasterData\Cloth;
use App\Models\MasterData\CMT;
use App\Models\MasterData\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FabricReturn extends Model
{
    use HasUuids;

    protected $table = 'trx_fabric_returns';

    protected $fillable = [
        'type',
        'cloth_id',
        'cmt_id',
        'quantity',
        'reason',
        'notes',
        'user_id',
    ];

    public function cloth()
    {
        return $this->belongsTo(Cloth::class, 'cloth_id')->withTrashed();
    }

    public function cmt()
    {
        return $this->belongsTo(CMT::class, 'cmt_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
