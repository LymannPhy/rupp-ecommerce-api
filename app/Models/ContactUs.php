<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContactUs extends Model
{
    use HasFactory;

    protected $table = 'contact_us';

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'message',
        'status'
    ];

    /**
     * Auto-generate UUID before creating a record.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($contact) {
            $contact->uuid = Str::uuid();
        });
    }
}
