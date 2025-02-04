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
        'subtotal'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2'
    ];

    // Relationship: Order Item belongs to an Order
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // Relationship: Order Item belongs to a Product
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
