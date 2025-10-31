<?php

namespace App\Models\Transactions;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderNote extends Model
{
    /** @use HasFactory<\Database\Factories\Transactions\OrderNoteFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'trx_order_notes';

    protected $fillable = ['notes', 'order_id', 'user_id'];

    // Define relationship with Order model
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    // Define relationship with User model
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
