<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Feedback extends Model
{
    use HasFactory;

    protected $table = 'feedbacks';

    protected $fillable = [
        'uuid',
        'user_id',
        'message',
        'status',
    ];

    /**
     * Automatically generate a UUID when creating a new feedback.
     */
    protected static function booted()
    {
        static::creating(function ($feedback) {
            $feedback->uuid = (string) Str::uuid();
        });
    }

    /**
     * Get the user who submitted the feedback.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

