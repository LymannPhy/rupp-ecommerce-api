<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Blog extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'title', 'content', 'image', 'youtube_videos', 'admin_id', 'status', 'published_at', 'is_deleted', 'views'
    ];

    protected $casts = [
        'youtube_videos' => 'array', 
        'published_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($blog) {
            $blog->uuid = (string) Str::uuid();
        });
    }

    public function bookmarks()
    {
        return $this->hasMany(Bookmark::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'blog_tag');
    }
}
