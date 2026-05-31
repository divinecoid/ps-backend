<?php

namespace App\Models\Transactions;

use App\Models\MasterData\Factory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FabricPurchaseRequest extends Model
{
    /** @use HasFactory<\Database\Factories\Transactions\FabricPurchaseRequestFactory> */
    use HasFactory, HasUuids;

    protected $table = 'trx_fabric_purchase_requests';

    protected $fillable = [
        'factory_id',
        'gram',
        'ukuran',
        'status',
    ];

    public function factory()
    {
        return $this->belongsTo(Factory::class, 'factory_id');
    }

    public function details()
    {
        return $this->hasMany(FabricPurchaseRequestDetail::class, 'fabric_purchase_request_id');
    }

    protected static function booted()
    {
        static::deleting(function (FabricPurchaseRequest $request) {
            $request->details()->delete();
        });
    }
}
