<?php

namespace App\Models\Transactions;

use App\Models\MasterData\CMT;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmtPayroll extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'trx_cmt_payrolls';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'cmt_id',
        'period_start',
        'period_end',
        'total_pcs',
        'total_amount',
        'status',
        'is_paid',
        'paid_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'paid_at' => 'datetime',
        'is_paid' => 'boolean',
    ];

    public function cmt()
    {
        return $this->belongsTo(CMT::class, 'cmt_id');
    }

    public function details()
    {
        return $this->hasMany(CmtPayrollDetail::class, 'cmt_payroll_id');
    }
}
