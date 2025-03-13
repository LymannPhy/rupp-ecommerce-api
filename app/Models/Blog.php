<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Blog extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'title', 'content', 'image', 'youtube_videos', 'user_id', 'status', 
        'published_at', 'is_deleted', 'views', 'is_awarded', 'awarded_at', 'awarded_by', 'award_type', 'award_rank'
    ];

    protected $casts = [
        'youtube_videos' => 'array',
        'published_at' => 'datetime',
        'awarded_at' => 'datetime',
        'is_awarded' => 'boolean'
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

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function awardedBy()
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'blog_tag');
    }

    public function likes()
    {
        return $this->hasMany(BlogLike::class);
    }

    public function comments()
    {
        return $this->hasMany(BlogComment::class)->whereNull('parent_id')->orderBy('created_at', 'desc');
    }
}
