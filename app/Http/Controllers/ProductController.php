<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\Category;
use App\Models\Discount;
use App\Http\Responses\ApiResponse;
use App\Helpers\PaginationHelper;

class ProductController extends Controller
{
    /**
     * Retrieve product details by UUID.
     * 
     * This method fetches a product's details, including its category and discount.
     * It excludes soft-deleted products from the response.
     *
     * @param string $uuid The UUID of the product.
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($uuid)
    {
        try {
            // Find product by UUID and exclude soft-deleted products
            $product = Product::where('uuid', $uuid)
                ->where('is_deleted', false)
                ->with(['category:id,uuid,name', 'discount:id,uuid,discount_percentage'])
                ->first();

            // If product does not exist, return 404 error
            if (!$product) {
                return ApiResponse::error('Product not found', [], 404);
            }

            // Format response
            return ApiResponse::sendResponse([
                'uuid' => $product->uuid,
                'category_uuid' => $product->category->uuid ?? null,
                'discount_uuid' => $product->discount->uuid ?? null,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'stock' => $product->stock,
                'glycemic_index' => $product->glycemic_index,
                'is_preorder' => $product->is_preorder,
                'preorder_duration' => $product->preorder_duration,
                'expiration_date' => $product->expiration_date,
                'image' => $product->image,
                'multi_images' => json_decode($product->multi_images, true) ?? [],
                'slogan' => $product->slogan,
                'health_benefits' => $product->health_benefits,
                'color' => $product->color,
                'size' => $product->size,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ], 'Product details retrieved successfully!');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve product details', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Soft delete a product by UUID.
     * 
     * This method marks a product as "deleted" instead of permanently removing it 
     * from the database. This helps preserve stock data and allows for potential restoration.
     *
     * @param string $uuid The UUID of the product to delete.
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($uuid)
    {
        try {
            $product = Product::where('uuid', $uuid)->first();

            if (!$product) {
                return ApiResponse::error('Product not found', [], 404);
            }

            $product->update(['is_deleted' => true]);

            return ApiResponse::sendResponse([], '🗑️ Product removed from listing but stock is preserved.');

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete product', ['error' => $e->getMessage()], 500);
        }
    }

    
    /**
     * Update an existing product by UUID.
     *
     * @param Request $request
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $uuid)
    {
        try {
            // Find product by UUID
            $product = Product::where('uuid', $uuid)->first();

            if (!$product) {
                return ApiResponse::error('Product not found', [], 404);
            }

            // Validate request data
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255|unique:products,name,' . $product->id,
                'description' => 'sometimes|required|string',
                'category_uuid' => 'sometimes|required|exists:categories,uuid',
                'discount_uuid' => 'nullable|exists:discounts,uuid',
                'price' => 'sometimes|required|numeric|min:0',
                'stock' => 'sometimes|required|integer|min:0',
                'glycemic_index' => 'nullable|numeric|min:0',
                'is_preorder' => 'boolean',
                'preorder_duration' => 'nullable|integer|min:1',
                'expiration_date' => 'nullable|date',
                'image' => 'nullable|string|max:255',
                'multi_images' => 'nullable|array',
                'multi_images.*' => 'string|max:255',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation Error', $validator->errors()->toArray(), 422);
            }

            // Retrieve category and discount by UUID
            $category = $request->category_uuid ? Category::where('uuid', $request->category_uuid)->first() : null;
            $discount = $request->discount_uuid ? Discount::where('uuid', $request->discount_uuid)->first() : null;

            // Update product details
            $product->update([
                'category_id' => $category ? $category->id : $product->category_id,
                'discount_id' => $discount ? $discount->id : $product->discount_id,
                'name' => $request->name ?? $product->name,
                'description' => $request->description ?? $product->description,
                'price' => $request->price ?? $product->price,
                'stock' => $request->stock ?? $product->stock,
                'glycemic_index' => $request->glycemic_index ?? $product->glycemic_index,
                'is_preorder' => $request->is_preorder ?? $product->is_preorder,
                'preorder_duration' => $request->preorder_duration ?? $product->preorder_duration,
                'expiration_date' => $request->expiration_date ?? $product->expiration_date,
                'image' => $request->image ?? $product->image,
                'multi_images' => !empty($request->multi_images) ? json_encode($request->multi_images) : $product->multi_images,
            ]);

            return ApiResponse::sendResponse([
                'uuid' => $product->uuid,
                'category_uuid' => $category->uuid ?? $product->category->uuid ?? null,
                'discount_uuid' => $discount->uuid ?? $product->discount->uuid ?? null,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'stock' => $product->stock,
                'glycemic_index' => $product->glycemic_index,
                'is_preorder' => $product->is_preorder,
                'preorder_duration' => $product->preorder_duration,
                'expiration_date' => $product->expiration_date,
                'image' => $product->image,
                'multi_images' => json_decode($product->multi_images, true),
                'created_at' => $product->created_at,
                'updated_at' => now(),
            ], 'Product updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update product', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Retrieve all products with custom pagination metadata.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Fetch all products that are not deleted
            $products = Product::where('is_deleted', false)
                ->with(['category:id,uuid,name', 'discount:id,uuid,discount_percentage'])
                ->paginate(10); // Optional pagination

            // Format product data
            $formattedProducts = $products->map(function ($product) {
                return [
                    'uuid' => $product->uuid,
                    'category_uuid' => $product->category->uuid ?? null,
                    'discount_uuid' => $product->discount->uuid ?? null,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'stock' => $product->stock,
                    'glycemic_index' => $product->glycemic_index,
                    'is_preorder' => $product->is_preorder,
                    'preorder_duration' => $product->preorder_duration,
                    'expiration_date' => $product->expiration_date,
                    'image' => $product->image,
                    'multi_images' => json_decode($product->multi_images, true) ?? [],
                    'slogan' => $product->slogan,
                    'health_benefits' => $product->health_benefits,
                    'color' => $product->color,
                    'size' => $product->size,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                ];
            });

            // Use helper function for pagination response
            return ApiResponse::sendResponse(
                PaginationHelper::formatPagination($products, $formattedProducts),
                'Products retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve products', ['error' => $e->getMessage()], 500);
        }
    }
    /**
     * Store a newly created product using UUID for category and discount.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate request data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:products,name',
            'description' => 'required|string',
            'category_uuid' => 'required|exists:categories,uuid', 
            'discount_uuid' => 'nullable|exists:discounts,uuid',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'is_preorder' => 'boolean',
            'preorder_duration' => 'nullable|integer|min:1',
            'expiration_date' => 'nullable|date',
            'image' => 'nullable|string|max:255', 
            'multi_images' => 'nullable|array',
            'multi_images.*' => 'string|max:255', 
        ]);

        // If validation fails, return a 422 response
        if ($validator->fails()) {
            return ApiResponse::error('Validation Error', $validator->errors()->toArray(), 422);
        }

        try {
            // Retrieve category and discount by UUID
            $category = Category::where('uuid', $request->category_uuid)->first();
            $discount = $request->discount_uuid ? Discount::where('uuid', $request->discount_uuid)->first() : null;

            // Create new product
            $product = Product::create([
                'uuid' => Str::uuid(),
                'category_id' => $category->id, 
                'discount_id' => $discount ? $discount->id : null,
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'stock' => $request->stock,
                'is_preorder' => $request->is_preorder ?? false,
                'preorder_duration' => $request->preorder_duration,
                'expiration_date' => $request->expiration_date,
                'image' => $request->image,
                'multi_images' => !empty($request->multi_images) ? json_encode($request->multi_images) : null, // Store multi_images as JSON
            ]);

            return ApiResponse::sendResponse([
                'uuid' => $product->uuid,
                'category_uuid' => $category->uuid,
                'discount_uuid' => $discount ? $discount->uuid : null,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'stock' => $product->stock,
                'is_preorder' => $product->is_preorder,
                'preorder_duration' => $product->preorder_duration,
                'expiration_date' => $product->expiration_date,
                'image' => $product->image,
                'multi_images' => json_decode($product->multi_images, true),
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ], 'Product created successfully 🎉', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create product', ['error' => $e->getMessage()], 500);
        }
    }
}

