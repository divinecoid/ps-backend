<?php

namespace App\Models\Transactions;

use App\Models\MasterData\Color;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FabricPurchaseRequestDetail extends Model
{
    /** @use HasFactory<\Database\Factories\Transactions\FabricPurchaseRequestDetailFactory> */
    use HasFactory, HasUuids;

    protected $table = 'trx_fabric_purchase_request_details';

    protected $fillable = [
        'fabric_purchase_request_id',
        'color_id',
        'quantity',
        'sequence',
    ];

    public function request()
    {
        return $this->belongsTo(FabricPurchaseRequest::class, 'fabric_purchase_request_id');
    }

    public function color()
    {
        return $this->belongsTo(Color::class, 'color_id');
    }
}
