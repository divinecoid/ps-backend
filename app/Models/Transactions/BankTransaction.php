<?php

namespace App\Models\Transactions;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BankTransaction extends Model
{
    use HasUuids;

    protected $table = 'trx_bank_transactions';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'transaction_date' => 'date:Y-m-d',
    ];
}
