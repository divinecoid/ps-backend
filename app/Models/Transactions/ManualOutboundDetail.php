<?php

namespace App\Models\Transactions;

use App\Models\MasterData\Color;
use App\Models\MasterData\Product;
use App\Models\MasterData\ProductModel;
use App\Models\MasterData\Size;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManualOutboundDetail extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'trx_manual_outbound_details';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'manual_outbound_id',
        'product_id',
        'barcode',
        'model_id',
        'color_id',
        'size_id',
    ];

    public function manualOutbound()
    {
        return $this->belongsTo(ManualOutbound::class, 'manual_outbound_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }

    public function model()
    {
        return $this->belongsTo(ProductModel::class, 'model_id');
    }

    public function color()
    {
        return $this->belongsTo(Color::class, 'color_id');
    }

    public function size()
    {
        return $this->belongsTo(Size::class, 'size_id');
    }
}
