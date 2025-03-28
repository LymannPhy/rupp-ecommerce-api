<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'code', 'discount_percentage', 'max_usage', 
        'user_limit', 'start_date', 'end_date', 'is_active',
        'image'
    ];

    /**
     * Get all orders where this coupon was used.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'coupon_users')
                    ->withPivot('usage_count')
                    ->withTimestamps();
    }

    /**
     * Check if the coupon is valid (active and within the date range).
     *
     * @return bool
     */
    public function isValid(): bool
    {
        $now = now();

        return $this->is_active &&
            (!$this->start_date || $this->start_date <= $now) &&
            (!$this->end_date || $this->end_date >= $now);
    }

    /**
     * Check if the coupon has reached its max usage limit.
     *
     * @return bool
     */
    public function hasReachedMaxUsage(): bool
    {
        if (!$this->max_usage) {
            return false; // No limit set
        }

        $usedCount = DB::table('orders')->where('coupon_id', $this->id)->count();
        return $usedCount >= $this->max_usage;
    }

    /**
     * Check if a user has exceeded their allowed usage for this coupon.
     *
     * @param int $userId
     * @return bool
     */
    public function hasUserExceededLimit(int $userId): bool
    {
        if (!$this->user_limit) {
            return false; // No user limit set
        }

        $userUsage = DB::table('orders')
            ->where('coupon_id', $this->id)
            ->where('user_id', $userId)
            ->count();

        return $userUsage >= $this->user_limit;
    }
}
