<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\Category;
use App\Models\Discount;
use App\Http\Responses\ApiResponse;
use App\Helpers\PaginationHelper;
use App\Models\ProductFeedback;
use App\Models\Supplier;
use Carbon\Carbon;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class ProductController extends Controller
{
    /**
     * Get all pre-order products with pagination.
     */
    public function getPreorderProducts(Request $request)
    {
        // Get paginated pre-order products
        $perPage = $request->query('page_size', 5);
        $preorderProducts = Product::where('is_preorder', true)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Extract required fields
        $formattedProducts = $preorderProducts->map(function ($product) {
            return [
                'uuid' => $product->uuid,
                'name' => $product->name,
                'description' => $product->description,
                'original_price' => $product->price,
                'discount_price' => $product->discount ? $product->price - ($product->price * $product->discount->percentage / 100) : $product->price,
                'is_preorder' => $product->is_preorder,
                'created_at' => $product->created_at,
            ];
        });

        // Format response using PaginationHelper
        $response = PaginationHelper::formatPagination($preorderProducts, $formattedProducts);

        return ApiResponse::sendResponse($response, 'Pre-order products retrieved successfully');
    }

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
            $products = $query->paginate($request->get('per_page', 5));

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
            $topProducts = $query->limit(5)->get();

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
     * Get all discounted products with search, filter, and pagination.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDiscountedProducts(Request $request)
    {
        try {
            // 🔹 Validate request parameters
            $validated = $request->validate([
                'search' => 'nullable|string|max:255',
                'category_id' => 'nullable|exists:categories,id',
                'sub_category_id' => 'nullable|exists:sub_categories,id',
                'min_price' => 'nullable|numeric|min:0',
                'max_price' => 'nullable|numeric|min:0',
                'page' => 'nullable|integer|min:1',
                'page_size' => 'nullable|integer|min:1|max:100',
            ]);

            // 🔹 Fetch discounted products with filters
            $query = Product::whereNotNull('discount_id')
                ->where('is_deleted', false)
                ->whereHas('discount', function ($q) {
                    $q->where('is_active', true)
                        ->whereDate('start_date', '<=', now())
                        ->whereDate('end_date', '>=', now());
                })
                ->with([
                    'discount:id,uuid,name,discount_percentage,start_date,end_date,is_active',
                    'category:id,name',
                ]);

            // 🔹 Apply search filter (by product name)
            if (!empty($validated['search'])) {
                $query->where('name', 'LIKE', '%' . $validated['search'] . '%');
            }

            // 🔹 Apply category filter
            if (!empty($validated['category_id'])) {
                $query->where('category_id', $validated['category_id']);
            }

            // 🔹 Apply sub-category filter (assuming sub_category_id exists in product model)
            if (!empty($validated['sub_category_id'])) {
                $query->where('sub_category_id', $validated['sub_category_id']);
            }

            // 🔹 Apply price range filter
            if (!empty($validated['min_price'])) {
                $query->where('price', '>=', $validated['min_price']);
            }
            if (!empty($validated['max_price'])) {
                $query->where('price', '<=', $validated['max_price']);
            }

            // 🔹 Paginate results
            $pageSize = 5;
            $discountedProducts = $query->paginate($pageSize);

            // 🔹 Format response data
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
                $singleImage = count($allImages) > 0 ? array_shift($allImages) : null; 

                return [
                    'uuid'               => $product->uuid,
                    'name'               => $product->name,
                    'description'        => $product->description,
                    'single_image'       => $singleImage, 
                    'images'             => $allImages, 
                    'price'              => $product->price,
                    'discounted_price'   => $discountedPrice,
                    'stock'              => $product->stock,
                    'category'           => $product->category->name ?? null,
                    'created_at'         => $product->created_at,
                    'updated_at'         => $product->updated_at,
                ];
            });

            // 🔹 Use Pagination Helper to format response
            return ApiResponse::sendResponse(
                $responseData,
                'Top 5 discounted products retrieved successfully'
            );            

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
                return ApiResponse::error('Product not found 🚫', ['details' => 'The requested product does not exist.'], 404);
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

            // ✅ Process multi-images, ensure it's always an array
            $multiImages = json_decode($product->multi_images, true);
            if (!is_array($multiImages)) {
                $multiImages = []; // Ensure it's always an array
            }

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

                    // ✅ Extract the first image from `multi_images` for similar products
                    $similarImages = json_decode($similarProduct->multi_images, true);
                    if (!is_array($similarImages) || empty($similarImages)) {
                        $similarFirstImage = null;
                    } else {
                        $similarFirstImage = $similarImages[0]; // Get only the first image
                    }

                    return [
                        'uuid' => $similarProduct->uuid,
                        'name' => $similarProduct->name,
                        'price' => $similarProduct->price,
                        'discount_percentage' => $similarProduct->discount->discount_percentage ?? 0,
                        'discounted_price' => $similarDiscountedPrice,
                        'single_image' => $similarFirstImage, 
                    ];
                });
            }

            // ✅ Retrieve the supplier
            $supplier = $product->supplier;
            $qrCodeBase64 = null;

            if ($supplier) {
                // ✅ Generate supplier profile URL
                $supplierProfileUrl = URL::to('/supplier/' . $supplier->uuid);

                // ✅ Generate QR Code
                $qrCode = QrCode::create($supplierProfileUrl)
                    ->setEncoding(new Encoding('UTF-8'))
                    ->setErrorCorrectionLevel(ErrorCorrectionLevel::Low)
                    ->setSize(300)
                    ->setMargin(10);

                // Generate PNG format
                $writer = new PngWriter();
                $qrCodeResult = $writer->write($qrCode);

                // Convert to Base64
                $qrCodeBase64 = 'data:image/png;base64,' . base64_encode($qrCodeResult->getString());
            }

            // ✅ Format supplier data including the QR code
            $supplierData = $supplier ? [
                'uuid' => $supplier->uuid,
                'name' => $supplier->name,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'address' => $supplier->address,
                'avatar' => $supplier->avatar,
                'qr_code' => $qrCodeBase64, 
            ] : null;

            // Format product response including similar products and the updated view count
            return ApiResponse::sendResponse([
                'uuid' => $product->uuid,
                'name' => $product->name,
                'category_name' => $product->category->name ?? null,
                'description' => $product->description,
                'price' => $product->price,
                'discounted_price' => $discountedPrice,
                'discount_start_date' => $product->discount ? DateHelper::formatDate($product->discount->start_date) : null,
                'discount_end_date' => $product->discount ? DateHelper::formatDate($product->discount->end_date) : null,
                'stock' => $product->stock,
                'is_preorder' => $product->is_preorder,
                'images' => $multiImages,
                'color' => $product->color,
                'size' => $product->size,
                'views' => $product->views,
                'average_rating' => round($averageRating, 2),
                'created_at' => DateHelper::formatDate($product->created_at),
                'updated_at' => DateHelper::formatDate($product->updated_at),
                'feedbacks' => $formattedFeedbacks,
                'similar_products' => $similarProducts,
                'supplier' => $supplierData,
            ], 'Product details retrieved successfully with discount price, top feedbacks, and similar products ✅');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve product details 🤯', ['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($uuid)
    {
        try {
            // Fetch the product by UUID
            $product = Product::where('uuid', $uuid)->first();

            if (!$product) {
                return ApiResponse::error('Product not found', [], 404);
            }

            DB::beginTransaction();

            // Permanently delete the product
            $product->forceDelete();

            DB::commit();

            return ApiResponse::sendResponse([], '🗑️ Product has been permanently deleted.');

        } catch (\Exception $e) {
            DB::rollBack();
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
                'is_preorder' => 'sometimes|boolean',
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

            // 🔍 Search by product name
            if ($request->has('search')) {
                $query->where('name', 'LIKE', '%' . $request->search . '%');
            }

            // 🔹 Filter by category and its subcategories
            if ($request->has('category_uuid')) {
                $category = Category::where('uuid', $request->category_uuid)->first();
                if ($category) {
                    $categoryIds = Category::where('parent_id', $category->id)->pluck('id')->toArray();
                    $categoryIds[] = $category->id;
                    $query->whereIn('category_id', $categoryIds);
                }
            }

            // 🔹 Filter by price range
            if ($request->has('min_price')) {
                $query->where('price', '>=', $request->min_price);
            }
            if ($request->has('max_price')) {
                $query->where('price', '<=', $request->max_price);
            }

            // 🔃 Sort by price
            if ($request->has('sort_price')) {
                $sortOrder = $request->sort_price === 'desc' ? 'desc' : 'asc';
                $query->orderBy('price', $sortOrder);
            }

            // 📦 Fetch all filtered products
            $products = $query->get();

            // 🧠 Format product response
            $formattedProducts = $products->map(function ($product) {
                $averageRating = ProductFeedback::where('product_id', $product->id)->avg('rating') ?? 0;

                $discountedPrice = null;
                if ($product->discount && isset($product->discount->discount_percentage)) {
                    $discountAmount = ($product->discount->discount_percentage / 100) * $product->price;
                    $discountedPrice = round($product->price - $discountAmount, 2);
                }

                $allImages = is_array($product->multi_images)
                    ? $product->multi_images
                    : json_decode($product->multi_images, true) ?? [];

                $singleImage = count($allImages) > 0 ? $allImages[0] : null;

                return [
                    'uuid' => $product->uuid,
                    'category_name' => $product->category->name ?? null,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'discount_percentage' => $product->discount->discount_percentage ?? 0,
                    'discounted_price' => $discountedPrice,
                    'stock' => $product->stock,
                    'is_preorder' => $product->is_preorder,
                    'single_image' => $singleImage,
                    'average_rating' => round($averageRating, 2),
                    'created_at' => $product->created_at,
                ];
            });

            return ApiResponse::sendResponse(
                $formattedProducts,
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
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'category_uuid' => 'required|exists:categories,uuid',
            'subcategory_uuid' => 'nullable|exists:categories,uuid',
            'discount_uuid' => 'nullable|exists:discounts,uuid',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'is_preorder' => 'boolean',
            'multi_images' => 'nullable|array',
            'multi_images.*' => 'string|max:255',
            'is_recommended' => 'boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation Error', $validator->errors()->toArray(), 422);
        }

        try {
            $category = Category::where('uuid', $request->category_uuid)->first();
            $subcategory = $request->subcategory_uuid ? Category::where('uuid', $request->subcategory_uuid)->first() : null;

            if ($subcategory && $subcategory->parent_id !== $category->id) {
                return ApiResponse::error('Invalid Subcategory ❌', ['subcategory_uuid' => 'This subcategory does not belong to the selected category.'], 400);
            }

            $discount = $request->discount_uuid ? Discount::where('uuid', $request->discount_uuid)->first() : null;

            $multiImages = $request->multi_images ?? [];
            if (!empty($multiImages)) {
                $multiImages = array_map(function ($image) {
                    return stripslashes(trim($image));
                }, $multiImages);
            }

            $product = Product::create([
                'uuid' => Str::uuid(),
                'category_id' => $subcategory ? $subcategory->id : $category->id,
                'discount_id' => $discount ? $discount->id : null,
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'stock' => $request->stock,
                'is_preorder' => $request->is_preorder ?? false,
                'multi_images' => json_encode($multiImages),
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

