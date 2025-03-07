<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Wishlist;
use App\Models\Product;
use App\Http\Responses\ApiResponse;
use App\Models\Cart;
use App\Models\ProductFeedback;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    /**
     * Move all wishlist products to cart.
     */
    public function moveWishlistToCart()
    {
        $user = Auth::user();

        // Fetch all wishlist items with product details
        $wishlistItems = Wishlist::where('user_id', $user->id)
            ->with('product')
            ->get();

        if ($wishlistItems->isEmpty()) {
            return ApiResponse::error('Wishlist is empty.', [], 400);
        }

        foreach ($wishlistItems as $item) {
            $product = $item->product;

            // Check if the product exists and has stock available
            if (!$product || $product->stock <= 0) {
                continue; // Skip out-of-stock products
            }

            // Check if product is already in cart
            $existingCartItem = Cart::where('user_id', $user->id)
                ->where('product_id', $product->id)
                ->first();

            if ($existingCartItem) {
                // Update quantity in cart
                $existingCartItem->increment('quantity');
            } else {
                // Add new item to cart
                Cart::create([
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'quantity' => 1, // Default quantity
                    'price' => $product->price,
                ]);
            }
        }

        // Remove all wishlist items after adding to cart
        Wishlist::where('user_id', $user->id)->delete();

        return ApiResponse::sendResponse([], 'All wishlist items moved to cart successfully.');
    }

    /**
     * Remove product from wishlist using wishlist UUID.
     */
    public function removeFromWishlist(Request $request)
    {
        $request->validate([
            'wishlist_uuid' => 'required|exists:wishlist,uuid',
        ]);

        $user = Auth::user();
        $wishlist_uuid = $request->wishlist_uuid;

        // Find the wishlist entry
        $wishlistItem = Wishlist::where('uuid', $wishlist_uuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $wishlistItem->delete();

        return ApiResponse::sendResponse([], 'Wishlist item removed successfully');
    }

    /**
     * Get the authenticated user's wishlist with product details.
     */
    public function getWishlist()
    {
        $user = Auth::user();

        $wishlist = Wishlist::where('user_id', $user->id)
            ->with(['product:id,uuid,name,price,multi_images', 'user:id,uuid'])
            ->get()
            ->map(function ($item) {
                $product = $item->product;

                // Get average rating
                $averageRating = ProductFeedback::where('product_id', $product->id)->avg('rating') ?? 0;

                // Process images
                $allImages = json_decode($product->multi_images, true) ?? [];
                $singleImage = count($allImages) > 0 ? $allImages[0] : null;

                return [
                    'wishlist_uuid' => $item->uuid, 
                    'user_uuid' => $item->user->uuid, 
                    'product_uuid' => $product->uuid,
                    'product_name' => $product->name, 
                    'product_price' => $product->price, 
                    'single_image' => $singleImage, 
                    'average_rating' => round($averageRating, 2),
                    'created_at' => $item->created_at, 
                ];
            });

        return ApiResponse::sendResponse($wishlist, 'Wishlist retrieved successfully');
    }

    /**
     * Add product to wishlist using UUID but store product ID.
     */
    public function addToWishlist(Request $request)
    {
        $request->validate([
            'product_uuid' => 'required|exists:products,uuid',
        ]);

        $user = Auth::user();
        $product_uuid = $request->product_uuid;

        // Retrieve the product ID using the UUID
        $product = Product::where('uuid', $product_uuid)->first();

        if (!$product) {
            return ApiResponse::error('Product not found', [], 404);
        }

        // Check if product is already in wishlist
        $existingWishlist = Wishlist::where('user_id', $user->id)
            ->where('product_id', $product->id) 
            ->first();

        if ($existingWishlist) {
            return ApiResponse::error('Product is already in wishlist');
        }

        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $product->id, 
        ]);

        // Return product UUID and user UUID 
        return ApiResponse::sendResponse([
            'user_uuid' => $user->uuid,
            'product_uuid' => $product->uuid,
        ], 'Product added to wishlist');
    }


}

