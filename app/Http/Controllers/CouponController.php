<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Coupon;
use App\Http\Responses\ApiResponse;
use Carbon\Carbon;

class CouponController extends Controller
{
    /**
     * Store a newly created coupon.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate request data
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:coupons,code|max:255',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'max_usage' => 'nullable|integer|min:1',
            'user_limit' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
            'is_active' => 'boolean',
        ]);

        // If validation fails, return a 422 response
        if ($validator->fails()) {
            return ApiResponse::error('Validation Error', $validator->errors()->toArray(), 422);
        }

        try {

            $startDate = $request->start_date ? Carbon::parse($request->start_date)->format('Y-m-d H:i:s') : null;
            $endDate = $request->end_date ? Carbon::parse($request->end_date)->format('Y-m-d H:i:s') : null;

            // Create a new coupon with UUID
            $coupon = Coupon::create([
                'uuid' => Str::uuid(),
                'code' => strtoupper($request->code), // Ensure coupon codes are stored in uppercase
                'discount_percentage' => $request->discount_percentage,
                'max_usage' => $request->max_usage,
                'user_limit' => $request->user_limit,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_active' => $request->is_active ?? true, // Default to active
            ]);

            // Return the created coupon response
            return ApiResponse::sendResponse([
                'uuid' => $coupon->uuid,
                'code' => $coupon->code,
                'discount_percentage' => $coupon->discount_percentage,
                'max_usage' => $coupon->max_usage,
                'user_limit' => $coupon->user_limit,
                'start_date' => $coupon->start_date,
                'end_date' => $coupon->end_date,
                'is_active' => $coupon->is_active,
                'created_at' => $coupon->created_at,
                'updated_at' => $coupon->updated_at,
            ], 'Coupon created successfully 🎉', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create coupon', ['error' => $e->getMessage()], 500);
        }
    }
}

