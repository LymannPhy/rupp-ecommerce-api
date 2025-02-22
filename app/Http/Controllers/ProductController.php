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
use App\Models\Rating;

class ProductController extends Controller
{
    /**
     * Get the latest products with filtering options.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLatestProducts(Request $request)
    {
        try {
            // Get filters from request
            $categoryUuid = $request->query('category_uuid');
            $subCategoryUuid = $request->query('subcategory_uuid');
            $minPrice = $request->query('min_price');
            $maxPrice = $request->query('max_price');
            $search = $request->query('search');

            // Build query
            $query = Product::where('is_deleted', false)
                ->with(['category:id,name', 'discount']) // Load category and discount details
                ->orderByDesc('created_at'); // Sort by newest first

            // 🔹 Filter by category
            if ($categoryUuid) {
                $category = Category::where('uuid', $categoryUuid)->first();
                if ($category) {
                    $query->where('category_id', $category->id);
                }
            }

            // 🔹 Filter by subcategory
            if ($subCategoryUuid) {
                $subCategory = Category::where('uuid', $subCategoryUuid)->first();
                if ($subCategory) {
                    $query->where('category_id', $subCategory->id);
                }
            }

            // 🔹 Filter by price range
            if ($minPrice !== null) {
                $query->where('price', '>=', (float) $minPrice);
            }
            if ($maxPrice !== null) {
                $query->where('price', '<=', (float) $maxPrice);
            }

            // 🔹 Search by product name
            if ($search) {
                $query->where('name', 'LIKE', '%' . $search . '%');
            }

            // Get the latest 10 products
            $latestProducts = $query->limit(10)->get();

            if ($latestProducts->isEmpty()) {
                return ApiResponse::error('No products found', [], 404);
            }

            // Format response data
            $responseData = $latestProducts->map(function ($product) {
                $discountedPrice = null;
                $discountPercentage = 0;

                // Validate if discount is active
                if ($product->discount) {
                    $discountPercentage = (float) $product->discount->discount_percentage;
                    $isActive = (bool) $product->discount->is_active;
                    $startDate = $product->discount->start_date ? \Carbon\Carbon::parse($product->discount->start_date) : null;
                    $endDate = $product->discount->end_date ? \Carbon\Carbon::parse($product->discount->end_date) : null;
                    $now = now();

                    // Check if discount is active and valid based on dates
                    if ($isActive && $startDate && $now >= $startDate && (!$endDate || $now <= $endDate)) {
                        // Calculate discount
                        $discountAmount = ($discountPercentage / 100) * $product->price;
                        $discountedPrice = round($product->price - $discountAmount, 2);
                    }
                }

                return [
                    'uuid' => $product->uuid,
                    'name' => $product->name,
                    'description' => $product->description,
                    'image' => $product->image,
                    'multi_images' => $product->multi_images,
                    'price' => $product->price, // Original price
                    'discount_percentage' => $discountPercentage,
                    'discounted_price' => $discountedPrice, // Null if no valid discount
                    'stock' => $product->stock,
                    'category' => $product->category->name ?? null,
                    'is_preorder' => $product->is_preorder,
                    'expiration_date' => $product->expiration_date,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                ];
            });

            return ApiResponse::sendResponse($responseData, 'Filtered products retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('An error occurred while fetching the products', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }


    /**
     * Get all discounted products with their details and discount percentage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDiscountedProducts()
    {
        try {
            // Fetch products that have an active discount
            $discountedProducts = Product::whereNotNull('discount_id')
                ->where('is_deleted', false)
                ->whereHas('discount', function ($query) {
                    $query->where('is_active', true)
                        ->whereDate('start_date', '<=', now()) // Ensure discount has started
                        ->whereDate('end_date', '>=', now()); // Ensure discount is still valid
                })
                ->with([
                    'discount:id,uuid,name,discount_percentage,start_date,end_date,is_active',
                    'category:id,name',
                ])
                ->get();

            if ($discountedProducts->isEmpty()) {
                return ApiResponse::error('No discounted products found', [], 404);
            }

            // Format response data
            $responseData = $discountedProducts->map(function ($product) {
                $discountedPrice = null;
                $discountPercentage = 0;

                // Validate discount
                if ($product->discount) {
                    $discountPercentage = (float) $product->discount->discount_percentage;
                    $isActive = (bool) $product->discount->is_active;
                    $startDate = $product->discount->start_date ? \Carbon\Carbon::parse($product->discount->start_date) : null;
                    $endDate = $product->discount->end_date ? \Carbon\Carbon::parse($product->discount->end_date) : null;
                    $now = now();

                    // Check if discount is active and valid based on dates
                    if ($isActive && $startDate && $now >= $startDate && (!$endDate || $now <= $endDate)) {
                        // Calculate discount
                        $discountAmount = ($discountPercentage / 100) * $product->price;
                        $discountedPrice = round($product->price - $discountAmount, 2);
                    }
                }

                return [
                    'uuid' => $product->uuid,
                    'name' => $product->name,
                    'description' => $product->description,
                    'image' => $product->image,
                    'multi_images' => $product->multi_images,
                    'price' => $product->price, // Original price
                    'discount_percentage' => $discountPercentage,
                    'discounted_price' => $discountedPrice, // Null if no valid discount
                    'stock' => $product->stock,
                    'category' => $product->category->name ?? null,
                    'is_preorder' => $product->is_preorder,
                    'expiration_date' => $product->expiration_date,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                ];
            });

            return ApiResponse::sendResponse($responseData, 'Discounted products retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('An error occurred while fetching discounted products', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }


    /**
     * Retrieve product details by UUID along with related products and average rating.
     * 
     * This method fetches a product's details, including its category, discount,
     * related products in the same category, and average rating.
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

            // Fetch related products in the same category, excluding the current product
            $relatedProducts = Product::where('category_id', $product->category_id)
                ->where('uuid', '!=', $uuid) // Exclude the current product
                ->where('is_deleted', false)
                ->limit(5) // Adjust the number of related products as needed
                ->get(['uuid', 'name', 'image', 'price']);

            // Calculate average rating
            $averageRating = Rating::where('product_id', $product->id)->avg('rating') ?? 0;

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
                'average_rating' => round($averageRating, 2), // Rounded average rating
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
                'related_products' => $relatedProducts, // Add related products
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
     * Retrieve all products with custom pagination metadata, including average rating.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = Product::where('is_deleted', false)
                ->with(['category:id,uuid,name', 'discount:id,uuid,discount_percentage']);

            // Apply search by product name
            if ($request->has('search')) {
                $query->where('name', 'LIKE', '%' . $request->search . '%');
            }

            // Apply category filter
            if ($request->has('category_uuid')) {
                $query->whereHas('category', function ($q) use ($request) {
                    $q->where('uuid', $request->category_uuid);
                });
            }

            // Apply sorting by price
            if ($request->has('sort_price')) {
                $sortOrder = $request->sort_price === 'desc' ? 'desc' : 'asc';
                $query->orderBy('price', $sortOrder);
            }

            // Paginate results
            $products = $query->paginate(10);

            // Format product data with average rating
            $formattedProducts = $products->map(function ($product) {
                $averageRating = Rating::where('product_id', $product->id)->avg('rating') ?? 0;

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
                    'average_rating' => round($averageRating, 2),
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
     * Store a newly created product with category and subcategory support.
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
            'subcategory_uuid' => 'nullable|exists:categories,uuid',
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

        if ($validator->fails()) {
            return ApiResponse::error('Validation Error', $validator->errors()->toArray(), 422);
        }

        try {
            // Retrieve category
            $category = Category::where('uuid', $request->category_uuid)->first();

            // Retrieve subcategory (if provided)
            $subcategory = $request->subcategory_uuid ? Category::where('uuid', $request->subcategory_uuid)->first() : null;

            // Validate that the subcategory belongs to the main category
            if ($subcategory && $subcategory->parent_id !== $category->id) {
                return ApiResponse::error('Invalid Subcategory ❌', ['subcategory_uuid' => 'This subcategory does not belong to the selected category.'], 400);
            }

            // Retrieve discount if provided
            $discount = $request->discount_uuid ? Discount::where('uuid', $request->discount_uuid)->first() : null;

            // Create new product
            $product = Product::create([
                'uuid' => Str::uuid(),
                'category_id' => $subcategory ? $subcategory->id : $category->id, // 🔹 Assign either category or subcategory
                'discount_id' => $discount ? $discount->id : null,
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'stock' => $request->stock,
                'is_preorder' => $request->is_preorder ?? false,
                'preorder_duration' => $request->preorder_duration,
                'expiration_date' => $request->expiration_date,
                'image' => $request->image,
                'multi_images' => !empty($request->multi_images) ? json_encode($request->multi_images) : null, 
            ]);

            return ApiResponse::sendResponse([
                'uuid' => $product->uuid,
                'category_uuid' => $category->uuid,
                'subcategory_uuid' => $subcategory ? $subcategory->uuid : null,
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

