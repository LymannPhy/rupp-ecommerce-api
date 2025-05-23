<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
        'discounted_price',
    ];

    // Relationship: OrderItem belongs to Order
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // Relationship: OrderItem belongs to Product
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

}
