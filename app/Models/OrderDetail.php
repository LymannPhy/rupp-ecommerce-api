<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    use HasFactory;

    protected $table = 'order_details'; 

    protected $fillable = [
        'order_id',
        'email',
        'phone_number',
        'province_id',
        'google_map_link',
        'remarks',
    ];

    /**
     * Define the relationship with the Order model.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Define the relationship with the Province model.
     */
    public function province()
    {
        return $this->belongsTo(Province::class, 'province_id');
    }
}
