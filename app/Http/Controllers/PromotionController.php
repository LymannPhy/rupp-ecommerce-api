<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Discount;
use App\Models\Coupon;
use Carbon\Carbon;

class PromotionController extends Controller
{
    // Load all valid discounts
    public function getAllDiscounts()
    {
        $discounts = Discount::where('is_active', true)
            ->where(function($query) {
                $now = Carbon::now();
                $query->whereNull('start_date')
                      ->orWhere('start_date', '<=', $now);
            })
            ->where(function($query) {
                $now = Carbon::now();
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', $now);
            })
            ->get();

        return ApiResponse::sendResponse($discounts, 'All discounts retrieved successfully');
    }

    // Load all coupons (started & upcoming)
    public function getAllCoupons()
    {
        $now = Carbon::now();

        $coupons = Coupon::where('is_active', true)
            ->where(function($query) use ($now) {
                $query->whereNull('start_date')
                      ->orWhere('start_date', '>=', $now)
                      ->orWhere('start_date', '<=', $now);
            })
            ->get();

        return ApiResponse::sendResponse($coupons, 'All coupons (started & upcoming) retrieved successfully');
    }
}

