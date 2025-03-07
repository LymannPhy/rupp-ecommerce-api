<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'coupon_id',         
        'total_price',
        'delivery_price',      
        'delivery_method',   
        'delivery_date',
        'status'
    ];
    

    // Automatically generate a UUID before saving to the database
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            // Check if the UUID is not already set and generate one
            if (!$order->uuid) {
                $order->uuid = (string) Str::uuid();
            }
        });
    }

    protected $casts = [
        'total_price' => 'decimal:2'
    ];

    // Relationship: Order belongs to a User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relationship: Order belongs to a Coupon
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function payment(): HasOne {
        return $this->hasOne(Payment::class);
    }

    public function details()
    {
        return $this->hasOne(OrderDetail::class);
    }

    /**
     * Relationship: Order has many OrderItems.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
