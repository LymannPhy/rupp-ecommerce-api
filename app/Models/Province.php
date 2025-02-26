<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Province extends Model
{
    use HasFactory;

    protected $table = 'provinces';

    protected $fillable = ['uuid', 'name']; 

    /**
     * Automatically generate a UUID when creating a new province.
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid();
            }
        });
    }

    /**
     * Define the relationship with the OrderDetail model.
     */
    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, 'province_id');
    }
}
