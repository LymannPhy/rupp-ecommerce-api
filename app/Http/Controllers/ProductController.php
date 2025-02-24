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
use App\Models\ProductFeedback;
use Carbon\Carbon;

class ProductController extends Controller
{
    /**
     * Retrieve recommended products sorted by creation date with pagination.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function recommended(Request $request)
    {
        try {
            $query = Product::where('is_deleted', false)
                ->where('is_recommended', true)
                ->with(['category:id,uuid,name', 'discount:id,uuid,discount_percentage'])
                ->orderBy('created_at', 'desc');

            // Paginate results; default page size is 10 or use per_page from request
            $products = $query->paginate($request->get('per_page', 10));

            // Format each product data
            $formattedProducts = $products->map(function ($product) {
                // Calculate discounted price if a discount is set
                $discountedPrice = null;
                if ($product->discount && isset($product->discount->discount_percentage)) {
                    $discountAmount = ($product->discount->discount_percentage / 100) * $product->price;
                    $discountedPrice = round($product->price - $discountAmount, 2);
                }
                
                // Calculate average rating (if applicable)
                $averageRating = ProductFeedback::where('product_id', $product->id)->avg('rating') ?? 0;

                // ✅ Decode multi_images column correctly
                $allImages = json_decode($product->multi_images, true); // Decode JSON to array
                if (!is_array($allImages)) {
                    $allImages = []; // Ensure it defaults to an empty array
                }
                $singleImage = count($allImages) > 0 ? array_shift($allImages) : null; // Get first image

                return [
                    'uuid' => $product->uuid,
                    'category_name' => $product->category->name ?? null,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'discount_percentage' => $product->discount->discount_percentage ?? 0,
                    'discounted_price' => $discountedPrice,
                    'stock' => $product->stock,
                    'is_recommended' => $product->is_recommended,
                    'average_rating' => round($averageRating, 2),
                    'single_image' => $singleImage, // ✅ Correct first image
                    'images' => $allImages, // ✅ Remaining images
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                ];
            });

            return ApiResponse::sendResponse(
                \App\Helpers\PaginationHelper::formatPagination($products, $formattedProducts),
                'Recommended products retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve recommended products', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Get the top products based on orders, views, rating, and product feedback with filtering options.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPopularProducts(Request $request)
    {
        try {
            // Get filters from request
            $categoryUuid = $request->query('category_uuid');
            $subCategoryUuid = $request->query('subcategory_uuid');
            $minPrice = $request->query('min_price');
            $maxPrice = $request->query('max_price');
            $search = $request->query('search');

            // Build query with eager loading and additional counts/averages
            $query = Product::where('is_deleted', false)
                ->with(['category:id,name', 'discount'])
                ->withCount('orderItems')
                ->withCount('feedbacks')
                ->withAvg('feedbacks', 'rating');

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

            // Order by a composite popularity score:
            // Composite Score = (orderItems_count * 3) + (views * 0.1) + (COALESCE(feedbacks_avg_rating, 0) * 2) + (feedbacks_count)
            $query->orderByRaw("((order_items_count * 3) + (views * 0.1) + (COALESCE(feedbacks_avg_rating, 0) * 2) + (feedbacks_count)) DESC");

            // Get the top 10 products
            $topProducts = $query->limit(10)->get();

            if ($topProducts->isEmpty()) {
                return ApiResponse::error('No products found', [], 404);
            }

            // Format response data
            $responseData = $topProducts->map(function ($product) {
                $discountedPrice = null;
                $discountPercentage = 0;

                // ✅ Handle Discount Calculation
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

                // ✅ Process Images
                $allImages = json_decode($product->multi_images, true);
                if (!is_array($allImages)) {
                    $allImages = []; // Ensure it's an array
                }
                $singleImage = count($allImages) > 0 ? array_shift($allImages) : null; // Get first image

                return [
                    'uuid'               => $product->uuid,
                    'name'               => $product->name,
                    'description'        => $product->description,
                    'single_image'       => $singleImage,  
                    'images'             => $allImages,  
                    'price'              => $product->price,
                    'discount_percentage'=> $discountPercentage,
                    'discounted_price'   => $discountedPrice,
                    'stock'              => $product->stock,
                    'category'           => $product->category->name ?? null,
                    'is_preorder'        => $product->is_preorder,
                    'expiration_date'    => $product->expiration_date,
                    'order_count'        => $product->order_items_count,
                    'views'              => $product->views,
                    'feedback_count'     => $product->feedbacks_count,
                    'average_rating'     => round($product->feedbacks_avg_rating ?? 0, 2),
                    'created_at'         => $product->created_at,
                    'updated_at'         => $product->updated_at,
                ];
            });

            return ApiResponse::sendResponse($responseData, 'Top products retrieved successfully');
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
                        ->whereDate('start_date', '<=', now())
                        ->whereDate('end_date', '>=', now());
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

                // ✅ Handle Discount Calculation
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

                // ✅ Process Images from `multi_images`
                $allImages = json_decode($product->multi_images, true);
                if (!is_array($allImages)) {
                    $allImages = []; // Ensure it's an array
                }
                $singleImage = count($allImages) > 0 ? array_shift($allImages) : null; // Get first image

                return [
                    'uuid'               => $product->uuid,
                    'name'               => $product->name,
                    'description'        => $product->description,
                    'single_image'       => $singleImage, // ✅ First image extracted
                    'images'             => $allImages, // ✅ Remaining images
                    'price'              => $product->price,
                    'discount_percentage'=> $discountPercentage,
                    'discounted_price'   => $discountedPrice,
                    'stock'              => $product->stock,
                    'category'           => $product->category->name ?? null,
                    'is_preorder'        => $product->is_preorder,
                    'expiration_date'    => $product->expiration_date,
                    'created_at'         => $product->created_at,
                    'updated_at'         => $product->updated_at,
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
     * Retrieve product details by UUID with discount price, top 3 highest-rated feedbacks, similar products,
     * and increment the view count.
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
                ->with([
                    'category:id,uuid,name', // Load category details
                    'discount:id,uuid,discount_percentage,is_active,start_date,end_date' // Load discount details
                ])
                ->first();

            // If product not found, return error
            if (!$product) {
                return ApiResponse::error('Product not found', [], 404);
            }
            
            // Increase view count and refresh the product instance to reflect the updated views
            $product->increment('views');
            $product->refresh();

            // Calculate discounted price
            $discountedPrice = null;
            if ($product->discount && $product->discount->is_active) {
                $now = now();
                $startDate = $product->discount->start_date ? Carbon::parse($product->discount->start_date) : null;
                $endDate = $product->discount->end_date ? Carbon::parse($product->discount->end_date) : null;

                if ((!$startDate || $now >= $startDate) && (!$endDate || $now <= $endDate)) {
                    $discountAmount = ($product->discount->discount_percentage / 100) * $product->price;
                    $discountedPrice = round($product->price - $discountAmount, 2);
                }
            }

            // ✅ Process Images from `multi_images`
            $allImages = json_decode($product->multi_images, true);
            if (!is_array($allImages)) {
                $allImages = []; // Ensure it's an array
            }
            $singleImage = count($allImages) > 0 ? array_shift($allImages) : null; // Get first image

            // Fetch top 3 highest-rated feedbacks (excluding deleted ones)
            $topFeedbacks = ProductFeedback::where('product_id', $product->id)
                ->where('is_deleted', false)
                ->with('user:id,uuid,name,avatar')
                ->orderByDesc('rating')
                ->orderByDesc('created_at')
                ->limit(3)
                ->get();

            // Format product feedback data
            $formattedFeedbacks = $topFeedbacks->map(function ($feedback) {
                return [
                    'uuid' => $feedback->uuid,
                    'user' => [
                        'uuid' => $feedback->user->uuid,
                        'name' => $feedback->user->name,
                        'avatar' => $feedback->user->avatar,
                    ],
                    'comment' => $feedback->comment,
                    'rating' => $feedback->rating,
                    'created_at' => $feedback->created_at,
                ];
            });

            // Calculate average rating
            $averageRating = ProductFeedback::where('product_id', $product->id)
                ->where('is_deleted', false)
                ->avg('rating') ?? 0;

            // Query similar products based on category (excluding the current product)
            $similarProducts = [];
            if ($product->category) {
                $similarProductsQuery = Product::where('category_id', $product->category->id)
                    ->where('uuid', '<>', $product->uuid)
                    ->where('is_deleted', false)
                    ->with([
                        'discount:id,uuid,discount_percentage,is_active,start_date,end_date'
                    ])
                    ->limit(5)
                    ->get();

                // Format similar product data
                $similarProducts = $similarProductsQuery->map(function ($similarProduct) {
                    $similarDiscountedPrice = null;
                    if ($similarProduct->discount && $similarProduct->discount->is_active) {
                        $now = now();
                        $startDate = $similarProduct->discount->start_date ? Carbon::parse($similarProduct->discount->start_date) : null;
                        $endDate = $similarProduct->discount->end_date ? Carbon::parse($similarProduct->discount->end_date) : null;

                        if ((!$startDate || $now >= $startDate) && (!$endDate || $now <= $endDate)) {
                            $discountAmount = ($similarProduct->discount->discount_percentage / 100) * $similarProduct->price;
                            $similarDiscountedPrice = round($similarProduct->price - $discountAmount, 2);
                        }
                    }

                    // ✅ Extract first image from `multi_images`
                    $similarImages = json_decode($similarProduct->multi_images, true);
                    $similarFirstImage = is_array($similarImages) && count($similarImages) > 0 ? $similarImages[0] : null;

                    return [
                        'uuid' => $similarProduct->uuid,
                        'name' => $similarProduct->name,
                        'price' => $similarProduct->price,
                        'discount_percentage' => $similarProduct->discount->discount_percentage ?? 0,
                        'discounted_price' => $similarDiscountedPrice,
                        'image' => $similarFirstImage, 
                    ];
                });
            }

            // Format product response including similar products and the updated view count
            return ApiResponse::sendResponse([
                'uuid' => $product->uuid,
                'name' => $product->name,
                'category_name' => $product->category->name ?? null,
                'description' => $product->description,
                'price' => $product->price,
                'discount_percentage' => $product->discount->discount_percentage ?? 0,
                'discounted_price' => $discountedPrice,
                'stock' => $product->stock,
                'is_preorder' => $product->is_preorder,
                'preorder_duration' => $product->preorder_duration,
                'expiration_date' => $product->expiration_date,
                'single_image' => $singleImage, 
                'images' => $allImages,
                'color' => $product->color,
                'size' => $product->size,
                'views' => $product->views, 
                'average_rating' => round($averageRating, 2),
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
                'feedbacks' => $formattedFeedbacks,
                'similar_products' => $similarProducts,
            ], 'Product details retrieved successfully with discount price, top feedbacks, and similar products ✅');
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
                'is_preorder' => 'sometimes|boolean',
                'preorder_duration' => 'nullable|integer|min:1',
                'expiration_date' => 'nullable|date',
                'multi_images' => 'nullable|array',
                'multi_images.*' => 'string|max:255',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation Error', $validator->errors()->toArray(), 422);
            }

            // Retrieve category and discount by UUID
            $category = $request->category_uuid ? Category::where('uuid', $request->category_uuid)->first() : null;
            $discount = $request->discount_uuid ? Discount::where('uuid', $request->discount_uuid)->first() : null;

            // Ensure category and discount exist before updating
            if ($request->has('category_uuid') && !$category) {
                return ApiResponse::error('Invalid category UUID', [], 400);
            }
            if ($request->has('discount_uuid') && !$discount) {
                return ApiResponse::error('Invalid discount UUID', [], 400);
            }

            // Handle `multi_images` properly (convert to JSON format)
            $multiImages = $request->has('multi_images') ? json_encode($request->multi_images) : $product->multi_images;

            // Update product details
            $product->update([
                'category_id' => $category ? $category->id : $product->category_id,
                'discount_id' => $discount ? $discount->id : $product->discount_id,
                'name' => $request->name ?? $product->name,
                'description' => $request->description ?? $product->description,
                'price' => $request->price ?? $product->price,
                'stock' => $request->stock ?? $product->stock,
                'is_preorder' => filter_var($request->is_preorder, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $product->is_preorder,
                'preorder_duration' => $request->preorder_duration ?? $product->preorder_duration,
                'expiration_date' => $request->expiration_date ?? $product->expiration_date,
                'multi_images' => $multiImages,
            ]);

            return ApiResponse::sendResponse([
                'uuid' => $product->uuid,
                'category_uuid' => $category->uuid ?? $product->category->uuid ?? null,
                'discount_uuid' => $discount->uuid ?? $product->discount->uuid ?? null,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'stock' => $product->stock,
                'is_preorder' => $product->is_preorder,
                'preorder_duration' => $product->preorder_duration,
                'expiration_date' => $product->expiration_date,
                'single_image' => $request->multi_images ? ($request->multi_images[0] ?? null) : (json_decode($product->multi_images, true)[0] ?? null),
                'images' => json_decode($product->multi_images, true) ?? [],
                'created_at' => $product->created_at,
                'updated_at' => now(),
            ], 'Product updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update product', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Retrieve all products with custom pagination metadata, including average rating and discount price.
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

            // Format product data with average rating and discount price calculation
            $formattedProducts = $products->map(function ($product) {
                $averageRating = ProductFeedback::where('product_id', $product->id)->avg('rating') ?? 0;

                // Calculate discounted price if a discount exists
                $discountedPrice = null;
                if ($product->discount && isset($product->discount->discount_percentage)) {
                    $discountAmount = ($product->discount->discount_percentage / 100) * $product->price;
                    $discountedPrice = round($product->price - $discountAmount, 2);
                }

                return [
                    'uuid' => $product->uuid,
                    'category_name' => $product->category->name ?? null,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'discount_percentage' => $product->discount->discount_percentage ?? 0,
                    'discounted_price' => $discountedPrice,
                    'stock' => $product->stock,
                    'glycemic_index' => $product->glycemic_index,
                    'is_preorder' => $product->is_preorder,
                    'preorder_duration' => $product->preorder_duration,
                    'expiration_date' => $product->expiration_date,
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
            'multi_images' => 'nullable|array',
            'multi_images.*' => 'string|max:255',
            'is_recommended' => 'boolean',
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
                'category_id' => $subcategory ? $subcategory->id : $category->id, 
                'discount_id' => $discount ? $discount->id : null,
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'stock' => $request->stock,
                'is_preorder' => $request->is_preorder ?? false,
                'preorder_duration' => $request->preorder_duration,
                'expiration_date' => $request->expiration_date,
                'multi_images' => !empty($request->multi_images) ? json_encode($request->multi_images) : null,
                'is_recommended' => $request->is_recommended ?? false, 
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
                'multi_images' => json_decode($product->multi_images, true),
                'is_recommended' => $product->is_recommended, 
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ], 'Product created successfully 🎉', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create product', ['error' => $e->getMessage()], 500);
        }
    }


}

