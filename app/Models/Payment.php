<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model {
    use HasFactory;

    protected $fillable = [
         'user_id', 'order_id', 'payment_method', 'amount', 'status', 'md5_hash', 'transaction_hash', 'from_account_id', 'to_account_id', 'user_id', 'transaction_place'
    ];

    public function order(): BelongsTo {
        return $this->belongsTo(Order::class);
    }
}

