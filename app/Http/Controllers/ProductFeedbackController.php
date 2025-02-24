<?php

namespace App\Http\Controllers;

use App\Helpers\PaginationHelper;
use Illuminate\Http\Request;
use App\Models\ProductFeedback;
use App\Models\Product;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class ProductFeedbackController extends Controller
{
    /**
     * Soft delete a product feedback by UUID.
     *
     * @param string $uuid The UUID of the feedback.
     * @return \Illuminate\Http\JsonResponse
     */
    public function softDelete($uuid)
    {
        try {
            // Find product feedback by UUID
            $feedback = ProductFeedback::where('uuid', $uuid)->first();

            // If feedback does not exist, return 404 error
            if (!$feedback) {
                return ApiResponse::error('Feedback not found', [], 404);
            }

            // Perform soft delete
            $feedback->update(['is_deleted' => true]);

            return ApiResponse::sendResponse([], 'Feedback soft deleted successfully ✅');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to soft delete feedback', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Retrieve all product feedbacks with user and product details.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllFeedbacks()
    {
        try {
            // Fetch feedbacks that are NOT deleted with related user and product details
            $feedbacks = ProductFeedback::where('is_deleted', false) // Exclude soft-deleted feedbacks
                ->with([
                    'user:id,uuid,name,avatar',
                    'product:id,uuid,name'
                ])
                ->orderByDesc('created_at')
                ->paginate(10); // Paginate results

            // Check if there are no feedbacks
            if ($feedbacks->isEmpty()) {
                return ApiResponse::error('No feedbacks found', [], 404);
            }

            // Format the feedback response
            $formattedFeedbacks = $feedbacks->map(function ($feedback) {
                return [
                    'uuid' => $feedback->uuid,
                    'user' => [
                        'uuid' => $feedback->user->uuid,
                        'name' => $feedback->user->name,
                        'avatar' => $feedback->user->avatar,
                    ],
                    'product' => [
                        'uuid' => $feedback->product->uuid,
                        'name' => $feedback->product->name,
                    ],
                    'comment' => $feedback->comment,
                    'rating' => $feedback->rating,
                    'created_at' => $feedback->created_at,
                ];
            });

            // Use PaginationHelper to format response
            return ApiResponse::sendResponse(
                PaginationHelper::formatPagination($feedbacks, $formattedFeedbacks),
                'All product feedbacks retrieved successfully ✅'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve feedbacks', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Store a new product feedback.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate request data
        $validator = Validator::make($request->all(), [
            'product_uuid' => 'required|exists:products,uuid',
            'comment' => 'nullable|string|max:1000',
            'rating' => 'required|integer|min:1|max:5',
        ], [
            'product_uuid.exists' => 'The selected product does not exist.',
            'rating.min' => 'Rating must be at least 1.',
            'rating.max' => 'Rating must not be greater than 5.',
        ]);

        // Return validation errors
        if ($validator->fails()) {
            return ApiResponse::error('Validation Error', $validator->errors()->toArray(), 422);
        }

        try {
            // Find product
            $product = Product::where('uuid', $request->product_uuid)->first();

            if (!$product) {
                return ApiResponse::error('Product not found ❌', [], 404);
            }

            // Create feedback
            $feedback = ProductFeedback::create([
                'uuid' => \Illuminate\Support\Str::uuid(),
                'product_id' => $product->id,
                'user_id' => Auth::id(),
                'comment' => $request->comment,
                'rating' => $request->rating,
            ]);

            return ApiResponse::sendResponse([
                'uuid' => $feedback->uuid,
                'product_uuid' => $product->uuid,
                'user_uuid' => Auth::user()->uuid,
                'comment' => $feedback->comment,
                'rating' => $feedback->rating,
                'created_at' => $feedback->created_at,
            ], 'Feedback submitted successfully 🎉', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to submit feedback', ['error' => $e->getMessage()], 500);
        }
    }
}
