<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Payment extends Model {
    use HasFactory;

    protected $fillable = [
        'uuid', 'order_id', 'payment_method', 'amount', 'status', 'md5_hash', 'qr_code', 'user_id' 
    ];

    protected static function boot() {
        parent::boot();
        static::creating(fn ($payment) => $payment->uuid = Str::uuid());
    }

    public function order(): BelongsTo {
        return $this->belongsTo(Order::class);
    }
}

