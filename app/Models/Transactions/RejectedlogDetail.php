<?php

namespace App\Models\Transactions;

use App\Models\MasterData\Color;
use App\Models\MasterData\ProductModel;
use App\Models\MasterData\Size;
use App\Models\MasterData\Product;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RejectedlogDetail extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'trx_rejectedlog_details';

    protected $fillable = [
        'receivedlog_id',
        'request_detail_id',
        'model_id',
        'color_id',
        'size_id',
        'qty',
        'barcode'
    ];

    // Relationships
    public function receivedlog()
    {
        return $this->belongsTo(Receivedlog::class, 'receivedlog_id');
    }

    public function requestDetail()
    {
        return $this->belongsTo(RequestDetail::class, 'request_detail_id');
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

    public function product()
    {
        return $this->hasOne(Product::class, 'barcode', 'barcode');
    }
}
