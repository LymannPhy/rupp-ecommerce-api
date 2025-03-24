<?php

namespace App\Http\Controllers;

use App\Helpers\PaginationHelper;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * Create a subcategory under an existing category.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createSubcategory(Request $request)
    {
        // Validate request data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:categories,name|max:255',
            'parent_uuid' => 'required|exists:categories,uuid', 
        ]);

        // If validation fails, return a 422 response
        if ($validator->fails()) {
            return ApiResponse::error('Validation Error', $validator->errors()->toArray(), 422);
        }

        try {
            // Retrieve the parent category by UUID
            $parentCategory = Category::where('uuid', $request->parent_uuid)->first();

            // Create subcategory under parent category
            $subcategory = Category::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(), 
                'name' => $request->name,
                'parent_id' => $parentCategory->id, 
            ]);

            // Return response with created subcategory data
            return ApiResponse::sendResponse([
                'uuid' => $subcategory->uuid,
                'name' => $subcategory->name,
                'parent_uuid' => $parentCategory->uuid,
                'is_deleted' => $subcategory->is_deleted,
                'created_at' => $subcategory->created_at,
                'updated_at' => $subcategory->updated_at,
            ], 'Subcategory created successfully 🎉', 201);
        } catch (\Exception $e) {
            // Handle unexpected errors
            return ApiResponse::error('Failed to create subcategory', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a category by UUID.
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($uuid)
    {
        try {
            // Find category by UUID
            $category = Category::where('uuid', $uuid)->first();

            // If category not found, return a 404 response
            if (!$category) {
                return ApiResponse::error('Category not found', [], 404);
            }

            // Delete category
            $category->delete();

            // Return success response
            return ApiResponse::sendResponse([], 'Category deleted successfully ✅');
        } catch (\Exception $e) {
            // Handle unexpected errors
            return ApiResponse::error('Failed to delete category', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Update a category by UUID.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $uuid)
    {
        try {
            // Validate request data (only name field is required)
            $validated = $request->validate([
                'name' => 'required|string|max:255',
            ]);

            // Find category by UUID
            $category = Category::where('uuid', $uuid)->first();

            // If category not found, return a 404 response
            if (!$category) {
                return ApiResponse::error('Category not found', [], 404);
            }

            // Update category data
            $category->update($validated);

            // Return updated category response
            return ApiResponse::sendResponse([
                'uuid' => $category->uuid,
                'name' => $category->name,
                'is_deleted' => $category->is_deleted,
                'created_at' => $category->created_at,
                'updated_at' => now(),
            ], 'Category updated successfully ✅');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation errors (422 Unprocessable Entity)
            return ApiResponse::error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return ApiResponse::error('Failed to update category', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Retrieve a category by UUID (with subcategories if it's a parent).
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($uuid)
    {
        try {
            // 🔍 Find the category by UUID
            $category = Category::where('uuid', $uuid)->first();

            if (!$category) {
                return ApiResponse::error('Category not found', [], 404);
            }

            // 📦 Paginate subcategories (default 10 per page or use request param)
            $perPage = request()->get('per_page', 10);
            $subcategories = $category->subcategories()->paginate($perPage);

            // 🔄 Format subcategories
            $formattedSubcategories = $subcategories->getCollection()->map(function ($sub) {
                return [
                    'uuid' => $sub->uuid,
                    'name' => $sub->name,
                    'is_deleted' => $sub->is_deleted,
                    'created_at' => $sub->created_at,
                    'updated_at' => $sub->updated_at,
                ];
            })->toArray();

            // 🧭 Add pagination metadata
            $formattedWithPagination = PaginationHelper::formatPagination($subcategories, $formattedSubcategories);

            // ✅ Format main category
            $formattedCategory = [
                'uuid' => $category->uuid,
                'name' => $category->name,
                'is_deleted' => $category->is_deleted,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
                'subcategories' => $formattedWithPagination, // 👈 Paginated response
            ];

            return ApiResponse::sendResponse($formattedCategory, 'Category retrieved successfully ✅');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve category', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Get all categories with their subcategories.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            // ✅ Load all categories where parent_id is null (Parent Categories)
            $categories = Category::whereNull('parent_id')
                ->with(['subcategories']) // ✅ Load subcategories
                ->get();

            // ✅ Format categories with subcategories
            $formattedCategories = $categories->map(function ($category) {
                return [
                    'uuid' => $category->uuid,
                    'name' => $category->name,
                    'is_deleted' => $category->is_deleted,
                    'created_at' => $category->created_at,
                    'updated_at' => $category->updated_at,
                    'subcategories' => $category->subcategories->map(function ($sub) {
                        return [
                            'uuid' => $sub->uuid,
                            'name' => $sub->name,
                            'is_deleted' => $sub->is_deleted,
                            'created_at' => $sub->created_at,
                            'updated_at' => $sub->updated_at,
                        ];
                    }),
                ];
            });

            return ApiResponse::sendResponse($formattedCategories, 'Categories with subcategories loaded successfully ✅');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to load categories', ['error' => $e->getMessage()], 500);
        }
    }


   /**
     * Create a new category.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate request data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:categories,name|max:255',
        ]);

        // If validation fails, return a 422 response
        if ($validator->fails()) {
            return ApiResponse::error('Validation Error', $validator->errors()->toArray(), 422);
        }

        try {
            // Create a new category
            $category = Category::create([
                'name' => $request->name,
                'uuid' => (string) \Illuminate\Support\Str::uuid(), // Ensure UUID is generated
            ]);

            // Return created category response
            return ApiResponse::sendResponse([
                'uuid' => $category->uuid,
                'name' => $category->name,
                'is_deleted' => $category->is_deleted,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ], 'Category created successfully 🎉', 201);
        } catch (\Exception $e) {
            // Handle unexpected errors
            return ApiResponse::error('Failed to create category', ['error' => $e->getMessage()], 500);
        }
    }

}
