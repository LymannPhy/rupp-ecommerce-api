<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['uuid', 'name', 'is_deleted'];

    /**
     * Auto-generate UUID and Slug before creating a category.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            $category->uuid = Str::uuid();
        });
    }

    // Relationship with Product
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
