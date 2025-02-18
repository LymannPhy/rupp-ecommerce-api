<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Cart;
use App\Models\Product;
use App\Http\Responses\ApiResponse;

class CartController extends Controller
{
    /**
     * Update the quantity of a product in the authenticated user's cart.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateCartQuantity(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return ApiResponse::error('Unauthorized ❌', ['error' => 'User not authenticated'], 401);
            }

            // Validate request data
            $validator = Validator::make($request->all(), [
                'product_uuid' => 'required|exists:products,uuid',
                'quantity' => 'required|integer|min:1',
            ], [
                'product_uuid.exists' => 'The selected product does not exist.',
                'quantity.min' => 'Quantity must be at least 1.',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation Error ❌', $validator->errors()->toArray(), 422);
            }

            // Find the product
            $product = Product::where('uuid', $request->product_uuid)->first();

            if (!$product) {
                return ApiResponse::error('Product not found ❌', [], 404);
            }

            // Find cart item
            $cartItem = Cart::where('user_id', $user->id)
                            ->where('product_id', $product->id)
                            ->first();

            if (!$cartItem) {
                return ApiResponse::error('Product not found in cart ❌', [], 404);
            }

            // Ensure requested quantity does not exceed stock
            if ($request->quantity > $product->stock) {
                return ApiResponse::error('Insufficient stock ❌', [
                    'product_uuid' => $product->uuid,
                    'stock' => 'Only ' . $product->stock . ' units available.'
                ], 400);
            }

            // Update the cart quantity
            $cartItem->update(['quantity' => $request->quantity]);

            return ApiResponse::sendResponse([], 'Cart quantity updated successfully!');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update cart quantity 🔥', ['error' => $e->getMessage()], 500);
        }
    }

    // /**
    //  * Load all products in the authenticated user's cart.
    //  *
    //  * @return \Illuminate\Http\JsonResponse
    //  */
    // public function getCartItems()
    // {
    //     try {
    //         $user = auth()->user();

    //         if (!$user) {
    //             return ApiResponse::error('Unauthorized ❌', ['error' => 'User not authenticated'], 401);
    //         }

    //         // Fetch cart items with product details
    //         $cartItems = Cart::where('user_id', $user->id)
    //             ->join('products', 'cart.product_id', '=', 'products.id')
    //             ->select(
    //                 'products.name as product_name',
    //                 'products.image as product_image',
    //                 'cart.quantity',
    //                 'products.price'
    //             )
    //             ->get();

    //         return ApiResponse::sendResponse($cartItems, 'Cart items retrieved successfully!');
    //     } catch (\Exception $e) {
    //         return ApiResponse::error('Failed to load cart items 🔥', ['error' => $e->getMessage()], 500);
    //     }
    // }
    public function getCartItems()
    {
        try {
            // Fetch cart items without user authentication
            $cartItems = Cart::join('products', 'cart.product_id', '=', 'products.id')
                ->select(
                    'products.name as product_name',
                    'products.image as product_image',
                    'cart.quantity',
                    'products.price'
                )
                ->get();

            return ApiResponse::sendResponse($cartItems, 'Cart items retrieved successfully!');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to load cart items 🔥', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Clear all products from the user's cart.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearCart()
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return ApiResponse::error('Unauthorized ❌', ['error' => 'User not authenticated'], 401);
            }

            // Remove all items from cart
            Cart::where('user_id', $user->id)->delete();

            return ApiResponse::sendResponse([], 'Cart cleared successfully!');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to clear cart 🔥', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove a single product from the authenticated user's cart.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeFromCart(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return ApiResponse::error('Unauthorized ❌', ['error' => 'User not authenticated'], 401);
            }

            // Validate request data
            $validator = Validator::make($request->all(), [
                'product_uuid' => 'required|exists:products,uuid',
            ], [
                'product_uuid.exists' => 'The selected product does not exist.',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation Error ❌', $validator->errors()->toArray(), 422);
            }

            // Find the product
            $product = Product::where('uuid', $request->product_uuid)->first();

            if (!$product) {
                return ApiResponse::error('Product not found ❌', [], 404);
            }

            // Find cart item
            $cartItem = Cart::where('user_id', $user->id)
                            ->where('product_id', $product->id)
                            ->first();

            if (!$cartItem) {
                return ApiResponse::error('Product not found in cart ❌', [], 404);
            }

            // Remove product from cart
            $cartItem->delete();

            return ApiResponse::sendResponse([], 'Product removed from cart successfully!');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to remove product from cart 🔥', ['error' => $e->getMessage()], 500);
        }
    }


   /**
     * Add a single product to the user's cart.
     *
     * If the product already exists in the cart, it updates the quantity.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addToCart(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return ApiResponse::error('Unauthorized ❌', ['error' => 'User not authenticated'], 401);
            }

            // Validate request data for a single product at a time
            $validator = Validator::make($request->all(), [
                'product_uuid' => 'required|exists:products,uuid',
                'quantity' => 'required|integer|min:1',
            ], [
                'product_uuid.exists' => 'The selected product does not exist.',
                'quantity.min' => 'Quantity must be at least 1.',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation Error ❌', $validator->errors()->toArray(), 422);
            }

            // Retrieve product by UUID
            $product = Product::where('uuid', $request->product_uuid)->first();

            if (!$product) {
                return ApiResponse::error('Product not found ❌', ['product_uuid' => $request->product_uuid], 404);
            }

            // Check if requested quantity is available
            if ($request->quantity > $product->stock) {
                return ApiResponse::error('Insufficient stock ❌', [
                    'product_uuid' => $product->uuid,
                    'stock' => 'Only ' . $product->stock . ' units available.'
                ], 400);
            }

            // Check if the product is already in the cart
            $cartItem = Cart::where('user_id', $user->id)
                            ->where('product_id', $product->id)
                            ->first();

            if ($cartItem) {
                // Update quantity if already in the cart
                $newQuantity = $cartItem->quantity + $request->quantity;

                // Ensure new quantity doesn't exceed stock
                if ($newQuantity > $product->stock) {
                    return ApiResponse::error('Insufficient stock ❌', [
                        'product_uuid' => $product->uuid,
                        'stock' => 'Only ' . $product->stock . ' units available.'
                    ], 400);
                }

                $cartItem->update(['quantity' => $newQuantity]);
            } else {
                // Add new item to cart
                Cart::create([
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'quantity' => $request->quantity,
                ]);
            }

            return ApiResponse::sendResponse([], 'Product added to cart successfully!');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to add product to cart 🔥', ['error' => $e->getMessage()], 500);
        }
    }
}
