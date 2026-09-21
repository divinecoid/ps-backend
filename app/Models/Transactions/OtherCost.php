<?php

namespace App\Models\Transactions;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtherCost extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'trx_other_costs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'category',
        'reference_type',
        'reference_id',
        'amount',
        'notes',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
