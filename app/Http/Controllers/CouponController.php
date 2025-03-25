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
     * Update a coupon by UUID.
     *
     * @param Request $request
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $uuid)
    {
        // Validation rules
        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|string|max:255|unique:coupons,code,' . $uuid . ',uuid',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'max_usage' => 'nullable|integer|min:1',
            'user_limit' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
            'is_active' => 'boolean',
        ]);

        // If validation fails, return a 422 response
        if ($validator->fails()) {
            return ApiResponse::error('Validation Error ❌', $validator->errors()->toArray(), 422);
        }

        try {
            $coupon = Coupon::where('uuid', $uuid)->first();

            if (!$coupon) {
                return ApiResponse::error('Coupon not found ❌', [], 404);
            }

            // Format dates if provided
            $startDate = $request->start_date ? Carbon::parse($request->start_date)->format('Y-m-d H:i:s') : $coupon->start_date;
            $endDate = $request->end_date ? Carbon::parse($request->end_date)->format('Y-m-d H:i:s') : $coupon->end_date;

            // Update fields
            $coupon->update([
                'code' => $request->has('code') ? strtoupper($request->code) : $coupon->code,
                'discount_percentage' => $request->discount_percentage ?? $coupon->discount_percentage,
                'max_usage' => $request->max_usage ?? $coupon->max_usage,
                'user_limit' => $request->user_limit ?? $coupon->user_limit,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_active' => $request->has('is_active') ? $request->is_active : $coupon->is_active,
            ]);

            return ApiResponse::sendResponse([
                'uuid' => $coupon->uuid,
                'code' => $coupon->code,
                'discount_percentage' => $coupon->discount_percentage,
                'max_usage' => $coupon->max_usage,
                'user_limit' => $coupon->user_limit,
                'start_date' => $coupon->start_date,
                'end_date' => $coupon->end_date,
                'is_active' => $coupon->is_active,
                'updated_at' => $coupon->updated_at,
            ], 'Coupon updated successfully ✅');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update coupon ❌', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Delete a coupon by UUID.
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($uuid)
    {
        try {
            $coupon = Coupon::where('uuid', $uuid)->first();

            if (!$coupon) {
                return ApiResponse::error('Coupon not found ❌', [], 404);
            }

            $coupon->delete();

            return ApiResponse::sendResponse([], 'Coupon deleted successfully 🗑️');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete coupon', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Retrieve a coupon by UUID.
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($uuid)
    {
        try {
            $coupon = Coupon::where('uuid', $uuid)->first();

            if (!$coupon) {
                return ApiResponse::error('Coupon not found', [], 404);
            }

            return ApiResponse::sendResponse([
                'uuid' => $coupon->uuid,
                'code' => $coupon->code,
                'discount_percentage' => $coupon->discount_percentage,
                'start_date' => $coupon->start_date,
                'end_date' => $coupon->end_date,
                'is_active' => $coupon->is_active,
            ], 'Coupon retrieved successfully ✅');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve coupon', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Retrieve all coupons with pagination.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $perPage = request()->get('per_page', 10);
            $coupons = Coupon::paginate($perPage);

            $formattedCoupons = $coupons->getCollection()->map(function ($coupon) {
                return [
                    'uuid' => $coupon->uuid,
                    'code' => $coupon->code,
                    'discount_percentage' => $coupon->discount_percentage,
                    'start_date' => $coupon->start_date,
                    'end_date' => $coupon->end_date,
                    'is_active' => $coupon->is_active,
                ];
            })->toArray();

            $response = \App\Helpers\PaginationHelper::formatPagination($coupons, $formattedCoupons);

            return ApiResponse::sendResponse($response, 'Coupons loaded successfully ✅');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to load coupons', ['error' => $e->getMessage()], 500);
        }
    }


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
                'code' => strtoupper($request->code), 
                'discount_percentage' => $request->discount_percentage,
                'max_usage' => $request->max_usage,
                'user_limit' => $request->user_limit,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_active' => $request->is_active ?? true,
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

