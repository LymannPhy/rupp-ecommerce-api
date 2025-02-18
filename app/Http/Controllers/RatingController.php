<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Rating;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RatingController extends Controller
{
    /**
     * Rate a product or update existing rating.
     *
     * @param Request $request
     * @param string $productUuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function rateProduct(Request $request, $productUuid)
    {
        $userId = Auth::id();
        $product = Product::where('uuid', $productUuid)->first();

        if (!$product) {
            return ApiResponse::error('Product not found', [], 404);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:500',
        ]);

        // Check if user already rated this product
        $rating = Rating::where('user_id', $userId)->where('product_id', $product->id)->first();

        if ($rating) {
            // Update existing rating
            $rating->update($validated);
            $message = 'Product rating updated successfully';
        } else {
            // Create new rating
            Rating::create([
                'user_id' => $userId,
                'product_id' => $product->id,
                'rating' => $validated['rating'],
                'review' => $validated['review'] ?? null,
            ]);
            $message = 'Product rated successfully';
        }

        return ApiResponse::sendResponse([], $message);
    }

    /**
     * Get average rating and reviews for a product.
     *
     * @param string $productUuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductRatings($productUuid)
    {
        $product = Product::where('uuid', $productUuid)->first();

        if (!$product) {
            return ApiResponse::error('Product not found', [], 404);
        }

        $ratings = Rating::where('product_id', $product->id)->with('user:id,uuid,name')->paginate(10);

        $averageRating = Rating::where('product_id', $product->id)->avg('rating');

        return ApiResponse::sendResponse([
            'average_rating' => round($averageRating, 2),
            'total_ratings' => $ratings->total(),
            'reviews' => $ratings->map(function ($rating) {
                return [
                    'uuid' => $rating->uuid,
                    'rating' => $rating->rating,
                    'review' => $rating->review,
                    'user' => [
                        'uuid' => $rating->user->uuid,
                        'name' => $rating->user->name,
                    ],
                    'created_at' => $rating->created_at,
                ];
            }),
            'metadata' => [
                'current_page' => $ratings->currentPage(),
                'per_page' => $ratings->perPage(),
                'total' => $ratings->total(),
                'total_pages' => $ratings->lastPage(),
            ],
        ], 'Product ratings retrieved successfully');
    }
}
