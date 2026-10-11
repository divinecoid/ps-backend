<?php

namespace App\Models\Transactions;

use App\Models\MasterData\ProductModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmtPayrollDetail extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'trx_cmt_payroll_details';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'cmt_payroll_id',
        'request_detail_id',
        'received_log_detail_id',
        'model_id',
        'qty',
        'qty_lusin',
        'unit_fee',
        'amount',
    ];

    public function payroll()
    {
        return $this->belongsTo(CmtPayroll::class, 'cmt_payroll_id');
    }

    public function requestDetail()
    {
        return $this->belongsTo(RequestDetail::class, 'request_detail_id');
    }

    public function receivedlogDetail()
    {
        return $this->belongsTo(ReceivedlogDetail::class, 'received_log_detail_id');
    }

    public function model()
    {
        return $this->belongsTo(ProductModel::class, 'model_id');
    }
}
