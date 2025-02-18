<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Rating extends Model
{
    use HasFactory;

    protected $fillable = ['uuid', 'user_id', 'product_id', 'rating', 'review'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($rating) {
            $rating->uuid = (string) Str::uuid(); 
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

