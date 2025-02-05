<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CouponUser extends Model
{
    use HasFactory;

    protected $table = 'coupon_users'; 

    protected $fillable = [
        'user_id',
        'coupon_id',
        'usage_count',
    ];

    /**
     * Get the user who used the coupon.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the associated coupon.
     */
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }
}

