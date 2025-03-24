<?php

namespace App\Http\Controllers;

use App\Helpers\PaginationHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
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
            $discount = Discount::where('uuid', $uuid)->first();

            if (!$discount) {
                return ApiResponse::error('Discount not found', [], 404);
            }

            // Delete image if exists
            if ($discount->image) {
                Storage::delete($discount->image);
            }

            $discount->delete();

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
            $discount = Discount::where('uuid', $uuid)->first();

            if (!$discount) {
                return ApiResponse::error('Discount not found', [], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255|unique:discounts,name,' . $discount->id,
                'description' => 'nullable|string',
                'discount_percentage' => 'sometimes|required|numeric|min:0|max:100',
                'start_date' => 'sometimes|required|date|after_or_equal:today',
                'end_date' => 'sometimes|required|date|after:start_date',
                'is_active' => 'boolean',
                'image' => 'nullable|string', 
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation Error', $validator->errors()->toArray(), 422);
            }

            $data = $validator->validated();
            $discount->update($data);

            return ApiResponse::sendResponse([
                'uuid' => $discount->uuid,
                'name' => $discount->name,
                'description' => $discount->description,
                'discount_percentage' => $discount->discount_percentage,
                'start_date' => $discount->start_date,
                'end_date' => $discount->end_date,
                'is_active' => $discount->is_active,
                'image' => $discount->image,
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
            $discount = Discount::where('uuid', $uuid)->first();

            if (!$discount) {
                return ApiResponse::error('Discount not found', [], 404);
            }

            return ApiResponse::sendResponse([
                'uuid' => $discount->uuid,
                'name' => $discount->name,
                'description' => $discount->description,
                'discount_percentage' => $discount->discount_percentage,
                'start_date' => $discount->start_date,
                'end_date' => $discount->end_date,
                'is_active' => $discount->is_active,
                'image' => $discount->image,
                'created_at' => $discount->created_at,
                'updated_at' => $discount->updated_at,
            ], 'Discount retrieved successfully ✅');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve discount', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Retrieve all discounts with pagination.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            // 📦 Get paginated discounts (default 10 per page or from query param)
            $perPage = request()->get('per_page', 10);
            $discounts = Discount::paginate($perPage);

            // 🔄 Format each discount
            $formattedDiscounts = $discounts->getCollection()->map(function ($discount) {
                return [
                    'uuid' => $discount->uuid,
                    'name' => $discount->name,
                    'description' => $discount->description,
                    'discount_percentage' => $discount->discount_percentage,
                    'start_date' => $discount->start_date,
                    'end_date' => $discount->end_date,
                    'is_active' => $discount->is_active,
                    'image' => $discount->image,
                    'created_at' => $discount->created_at,
                    'updated_at' => $discount->updated_at,
                ];
            })->toArray();

            // 🧭 Add pagination metadata
            $response = PaginationHelper::formatPagination($discounts, $formattedDiscounts);

            return ApiResponse::sendResponse($response, 'Discounts loaded successfully ✅');
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
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:discounts,name',
            'description' => 'nullable|string',
            'discount_percentage' => 'required|numeric|min:0|max:100', 
            'start_date' => 'required|date|after_or_equal:today', 
            'end_date' => 'required|date|after:start_date', 
            'is_active' => 'boolean',
            'image' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation Error', $validator->errors()->toArray(), 422);
        }

        try {
            $data = $validator->validated();

            // Handle image upload if provided
            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')->store('discounts');
            }

            $discount = Discount::create(array_merge($data, ['uuid' => Str::uuid()]));

            return ApiResponse::sendResponse([
                'uuid' => $discount->uuid,
                'name' => $discount->name,
                'description' => $discount->description,
                'discount_percentage' => $discount->discount_percentage,
                'start_date' => $discount->start_date,
                'end_date' => $discount->end_date,
                'is_active' => $discount->is_active,
                'image' => $discount->image,
                'created_at' => $discount->created_at,
                'updated_at' => $discount->updated_at,
            ], 'Discount created successfully 🎉', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create discount', ['error' => $e->getMessage()], 500);
        }
    }
}
