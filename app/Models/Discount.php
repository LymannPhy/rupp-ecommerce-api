<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Discount extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'image',
        'discount_percentage',
        'start_date',
        'end_date',
        'is_active'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
    ];

    // Relationship with Product
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // Check if the discount is currently valid
    public function getIsValidAttribute()
    {
        $now = Carbon::now();
        return $this->is_active && (!$this->start_date || $now >= $this->start_date) && (!$this->end_date || $now <= $this->end_date);
    }
}
