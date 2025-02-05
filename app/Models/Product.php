<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'category_id',
        'discount_id',
        'name',
        'description',
        'image',
        'multi_images',
        'price',
        'stock',
        'glycemic_index',
        'is_preorder',
        'preorder_duration',
        'slogan',
        'health_benefits',
        'color',
        'size',
        'expiration_date',
        'is_deleted'
    ];

    protected $casts = [
        'multi_images' => 'array', 
        'is_preorder' => 'boolean',
    ];

    /**
     * Auto-generate UUID and Slug before creating a product.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            $product->uuid = Str::uuid();
        });
    }

    // ========================== Relationships ==========================

    // Relationship: Product belongs to a Category
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Relationship: Product belongs to a Discount
    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }

    // Relationship: Product is in many Order Items
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    // Relationship: Product is in many Carts
    public function cartItems()
    {
        return $this->hasMany(Cart::class);
    }

    // Relationship: Product is in many Wishlists
    public function wishlistItems()
    {
        return $this->hasMany(Wishlist::class);
    }
}
