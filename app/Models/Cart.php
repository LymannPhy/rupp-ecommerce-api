<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Cart extends Model
{
    use HasFactory;

    protected $table = 'cart'; 

    protected $fillable = [
        'uuid',
        'user_id',
        'product_id',
        'quantity'
    ];

    /**
     * Auto-generate UUID and Slug before creating a category.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($cart) {
            $cart->uuid = Str::uuid();
        });
    }

    // Relationship: Cart belongs to a User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relationship: Cart belongs to a Product
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
