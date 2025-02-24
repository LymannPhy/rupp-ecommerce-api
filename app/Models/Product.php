<?php

namespace App\Models;

use Carbon\Carbon;
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
        'multi_images',
        'views',
        'price',
        'stock',
        'is_preorder',
        'preorder_duration',
        'color',
        'size',
        'is_recommended',
        'expiration_date',
        'is_deleted',
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

    public function getIsValidAttribute()
    {
        $now = Carbon::now();
        return $this->is_active && (!$this->start_date || $now >= $this->start_date) && (!$this->end_date || $now <= $this->end_date);
    }


    // Relationship: Product belongs to a Category
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Relationship: Product belongs to a Discount
    public function discount()
    {
        return $this->belongsTo(Discount::class, 'discount_id', 'id');
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

    // Relationship: Product is in many ProductFeedback
    public function feedbacks()
    {
        return $this->hasMany(ProductFeedback::class, 'product_id');
    }

}
