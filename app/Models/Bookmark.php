<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Bookmark extends Model
{
    use HasFactory;

    protected $fillable = ['uuid', 'user_id', 'blog_id'];

    /**
     * Auto-generate UUID and Slug before creating a bookmark.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($bookmark) {
            $bookmark->uuid = Str::uuid();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function blog()
    {
        return $this->belongsTo(Blog::class);
    }
}
