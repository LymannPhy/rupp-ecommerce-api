<?php

namespace App\Models;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Contracts\JWTSubject;



class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid', 'name', 'email', 'password', 'avatar', 'address', 'phone_number', 'bio',
        'gender', 'date_of_birth', 'country', 'is_verified', 'is_blocked',
        'verification_code', 'verification_code_expiration', 'reset_password_code',
        'reset_password_code_expiration'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    protected static function boot()
    {
        parent::boot();

        // Generate UUID automatically when creating a user
        static::creating(function ($user) {
            if (empty($user->uuid)) {
                $user->uuid = Str::uuid();
            }
        });
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id'); 
    }

    public function hasRole($role)
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function coupons()
    {
        return $this->belongsToMany(Coupon::class, 'coupon_users')
                    ->withPivot('usage_count')
                    ->withTimestamps();
    }

    public function bookmarks()
    {
        return $this->hasMany(Bookmark::class);
    }

    // Define the relationship between User and Feedback
    public function feedbacks()
    {
        return $this->hasMany(Feedback::class);
    }

    // 🔹 User-Blog Like Relationship (One-to-Many)
    public function blogLikes()
    {
        return $this->hasMany(BlogLike::class);
    }

    // ✅ Check if User Liked a Blog Post
    public function hasLikedBlog($blogId)
    {
        return $this->blogLikes()->where('blog_id', $blogId)->exists();
    }

    // 🔹 User-Comment Relationship (One-to-Many)
    public function blogComments()
    {
        return $this->hasMany(BlogComment::class);
    }


}
