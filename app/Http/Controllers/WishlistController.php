<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Wishlist;
use App\Models\Product;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
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
            ->with(['product:id,uuid,name,price,image', 'user:id,uuid'])
            ->get()
            ->map(function ($item) {
                return [
                    'wishlist_uuid' => $item->uuid, 
                    'user_uuid' => $item->user->uuid, 
                    'product_uuid' => $item->product->uuid,
                    'product_name' => $item->product->name, 
                    'product_price' => $item->product->price, 
                    'product_image' => $item->product->image, 
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

