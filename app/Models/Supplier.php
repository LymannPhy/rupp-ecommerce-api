<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = ['uuid', 'name', 'email', 'phone', 'address', 'avatar'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($supplier) {
            $supplier->uuid = Str::uuid();
        });
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}

