<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Discount;
use App\Http\Responses\ApiResponse;

class DiscountController extends Controller
{
    /**
     * Delete a discount by UUID.
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($uuid)
    {
        try {
            // Find discount by UUID
            $discount = Discount::where('uuid', $uuid)->first();

            // If discount not found, return 404
            if (!$discount) {
                return ApiResponse::error('Discount not found', [], 404);
            }

            // Delete discount
            $discount->delete();

            // Return success response
            return ApiResponse::sendResponse([], 'Discount deleted successfully ✅');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete discount', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Update an existing discount by UUID.
     *
     * @param Request $request
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $uuid)
    {
        try {
            // Find discount by UUID
            $discount = Discount::where('uuid', $uuid)->first();

            // If discount not found, return 404
            if (!$discount) {
                return ApiResponse::error('Discount not found', [], 404);
            }

            // Validate request data
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255|unique:discounts,name,' . $discount->id,
                'description' => 'nullable|string',
                'discount_percentage' => 'sometimes|required|numeric|min:0|max:100',
                'start_date' => 'sometimes|required|date|after_or_equal:today',
                'end_date' => 'sometimes|required|date|after:start_date',
                'is_active' => 'boolean',
            ]);

            // If validation fails, return a 422 response
            if ($validator->fails()) {
                return ApiResponse::error('Validation Error', $validator->errors()->toArray(), 422);
            }

            // Update discount with validated data
            $discount->update($validator->validated());

            // Return updated discount response
            return ApiResponse::sendResponse([
                'uuid' => $discount->uuid,
                'name' => $discount->name,
                'description' => $discount->description,
                'discount_percentage' => $discount->discount_percentage,
                'start_date' => $discount->start_date,
                'end_date' => $discount->end_date,
                'is_active' => $discount->is_active,
                'created_at' => $discount->created_at,
                'updated_at' => now(),
            ], 'Discount updated successfully ✅');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update discount', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Retrieve a discount by UUID.
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($uuid)
    {
        try {
            // Find discount by UUID
            $discount = Discount::where('uuid', $uuid)->first();

            // If not found, return 404
            if (!$discount) {
                return ApiResponse::error('Discount not found', [], 404);
            }

            // Return discount data
            return ApiResponse::sendResponse([
                'uuid' => $discount->uuid,
                'name' => $discount->name,
                'description' => $discount->description,
                'discount_percentage' => $discount->discount_percentage,
                'start_date' => $discount->start_date,
                'end_date' => $discount->end_date,
                'is_active' => $discount->is_active,
                'created_at' => $discount->created_at,
                'updated_at' => $discount->updated_at,
            ], 'Discount retrieved successfully ✅');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve discount', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Retrieve all discounts.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            // Retrieve all discounts
            $discounts = Discount::all()->map(function ($discount) {
                return [
                    'uuid' => $discount->uuid,
                    'name' => $discount->name,
                    'description' => $discount->description,
                    'discount_percentage' => $discount->discount_percentage,
                    'start_date' => $discount->start_date,
                    'end_date' => $discount->end_date,
                    'is_active' => $discount->is_active,
                    'created_at' => $discount->created_at,
                    'updated_at' => $discount->updated_at,
                ];
            });

            return ApiResponse::sendResponse($discounts, 'Discounts loaded successfully ✅');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to load discounts', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created discount.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate request data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:discounts,name',
            'description' => 'nullable|string',
            'discount_percentage' => 'required|numeric|min:0|max:100', 
            'start_date' => 'required|date|after_or_equal:today', 
            'end_date' => 'required|date|after:start_date', 
            'is_active' => 'boolean',
        ]);

        // If validation fails, return a 422 response
        if ($validator->fails()) {
            return ApiResponse::error('Validation Error', $validator->errors()->toArray(), 422);
        }

        try {
            // Create a new discount with a UUID
            $discount = Discount::create([
                'uuid' => Str::uuid(),
                'name' => $request->name,
                'description' => $request->description,
                'discount_percentage' => $request->discount_percentage,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'is_active' => $request->is_active ?? true, 
            ]);

            // Return the created discount response
            return ApiResponse::sendResponse([
                'uuid' => $discount->uuid,
                'name' => $discount->name,
                'description' => $discount->description,
                'discount_percentage' => $discount->discount_percentage,
                'start_date' => $discount->start_date,
                'end_date' => $discount->end_date,
                'is_active' => $discount->is_active,
                'created_at' => $discount->created_at,
                'updated_at' => $discount->updated_at,
            ], 'Discount created successfully 🎉', 201);
        } catch (\Exception $e) {
            // Handle unexpected errors
            return ApiResponse::error('Failed to create discount', ['error' => $e->getMessage()], 500);
        }
    }
}
