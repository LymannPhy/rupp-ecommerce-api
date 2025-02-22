<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['uuid', 'name', 'parent_id', 'is_deleted'];

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

    /**
     * Get the parent category (if any).
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get subcategories of this category.
     */
    public function subcategories()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
}
