<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Wishlist extends Model
{
    use HasFactory;

    protected $table = 'wishlist'; 

    protected $fillable = [
        'uuid',
        'user_id',
        'product_id'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($wishlist) {
            $wishlist->uuid = Str::uuid();
        });
    }

    // Relationship: Wishlist belongs to a User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relationship: Wishlist belongs to a Product
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
