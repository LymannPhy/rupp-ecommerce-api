<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductFeedback extends Model
{
    use HasFactory;

    protected $table = 'product_feedbacks';

    protected $fillable = [
        'uuid',
        'product_id',
        'user_id',
        'comment',
        'rating',
        'is_deleted',
    ];

    /**
     * Get the product that owns the feedback.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who wrote the feedback.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
