<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Helpers\PaginationHelper;
use App\Models\Tag;

class BlogController extends Controller
{
    /**
     * Get the top 10 blogs with the most views.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTopBlogs()
    {
        $topBlogs = Blog::where('is_deleted', false)
            ->where('status', 'published') // Only published blogs
            ->orderByDesc('views') // Sort by most views
            ->limit(10) // Get top 10
            ->with(['admin:id,uuid,name,email,avatar']) // Load admin details
            ->get();

        // If no blogs exist, return an empty response instead of 404
        if ($topBlogs->isEmpty()) {
            return ApiResponse::sendResponse([], 'No top blogs available');
        }

        $responseData = $topBlogs->map(function ($blog) {
            return [
                'uuid' => $blog->uuid,
                'title' => $blog->title,
                'content' => $blog->content,
                'image' => $blog->image,
                'youtube_videos' => $blog->youtube_videos,
                'status' => $blog->status,
                'published_at' => $blog->published_at,
                'views' => $blog->views,
                'created_at' => $blog->created_at,
                'updated_at' => $blog->updated_at,
                'admin' => optional($blog->admin)->only(['uuid', 'name', 'email', 'avatar'])
            ];
        });

        return ApiResponse::sendResponse($responseData, 'Top 10 blogs retrieved successfully');
    }


    /**
     * Update a blog by UUID.
     *
     * @param Request $request
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $uuid)
    {
        // ✅ Validate input including tags
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'image' => 'nullable|string',
            'youtube_videos' => 'nullable|array',
            'youtube_videos.*' => 'url',
            'status' => 'nullable|in:draft,published',
            'tags' => 'nullable|array', // ✅ Tags should be an array
            'tags.*' => 'string|max:50' // ✅ Each tag should be a string
        ]);

        // ✅ Find the blog by UUID
        $blog = Blog::where('uuid', $uuid)->with('tags')->first();

        if (!$blog) {
            return ApiResponse::error('Blog not found', [], 404);
        }

        // ✅ Begin database transaction
        DB::beginTransaction();

        try {
            // ✅ Update blog post details
            $blog->update($request->only([
                'title', 'content', 'image', 'youtube_videos', 'status'
            ]));

            // ✅ Process Tags if provided
            if ($request->has('tags')) {
                $tagIds = [];

                foreach ($request->tags as $tagName) {
                    $tag = Tag::firstOrCreate(['name' => $tagName]); // Find or create the tag
                    $tagIds[] = $tag->id;
                }

                $blog->tags()->sync($tagIds); // ✅ Sync tags (add/remove as needed)
            }

            // ✅ Fetch admin user with UUID
            $admin = $blog->admin()->first(['uuid', 'name', 'email']);

            // ✅ Commit transaction
            DB::commit();

            return ApiResponse::sendResponse([
                'uuid' => $blog->uuid,
                'title' => $blog->title,
                'content' => $blog->content,
                'image' => $blog->image,
                'youtube_videos' => $blog->youtube_videos,
                'status' => $blog->status,
                'published_at' => $blog->published_at,
                'created_at' => $blog->created_at,
                'updated_at' => $blog->updated_at,
                'tags' => $blog->tags->pluck('name')->toArray(), // ✅ Return tags as an array
                'admin' => $admin
            ], 'Blog updated successfully');
            
        } catch (\Exception $e) {
            // ❌ Rollback transaction on failure
            DB::rollBack();
            return ApiResponse::error('Failed to update blog', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Soft delete a blog by UUID.
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($uuid)
    {
        // Find the blog by UUID
        $blog = Blog::where('uuid', $uuid)->first();

        if (!$blog) {
            return ApiResponse::error('Blog not found', [], 404);
        }

        try {
            // Soft delete the blog
            $blog->update(['is_deleted' => true]);

            return ApiResponse::sendResponse([], 'Blog deleted successfully');
            
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete blog', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Get all blogs that are not deleted with pagination.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10); // Default 10 per page

        $blogs = Blog::where('is_deleted', false)
            ->with(['admin:id,uuid,name,email,avatar'])
            ->paginate($perPage);

        if ($blogs->isEmpty()) {
            return ApiResponse::error('No blogs found', [], 404);
        }

        // Format response data
        $responseData = $blogs->map(function ($blog) {
            return [
                'uuid' => $blog->uuid,
                'title' => $blog->title,
                'content' => $blog->content,
                'image' => $blog->image,
                'youtube_videos' => $blog->youtube_videos,
                'status' => $blog->status,
                'published_at' => $blog->published_at,
                'views' => $blog->views,
                'created_at' => $blog->created_at,
                'updated_at' => $blog->updated_at,
                'admin' => [
                    'uuid' => $blog->admin->uuid,
                    'name' => $blog->admin->name,
                    'email' => $blog->admin->email,
                    'avatar' => $blog->admin->avatar
                ]
            ];
        });

        return ApiResponse::sendResponse(
            PaginationHelper::formatPagination($blogs, $responseData),
            'All active blogs retrieved successfully'
        );
    }


    /**
     * Show blog details by UUID.
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($uuid)
    {
        // Find the blog by UUID with tags
        $blog = Blog::where('uuid', $uuid)->with('tags')->first();

        if (!$blog) {
            return ApiResponse::error('Blog not found', [], 404);
        }

        // Increment views
        $blog->increment('views');

        // Fetch admin user with UUID
        $admin = $blog->admin()->first(['uuid', 'name', 'email']);

        return ApiResponse::sendResponse([
            'uuid' => $blog->uuid,
            'title' => $blog->title,
            'content' => $blog->content,
            'image' => $blog->image,
            'youtube_videos' => $blog->youtube_videos,
            'status' => $blog->status,
            'published_at' => $blog->published_at,
            'views' => $blog->views,
            'created_at' => $blog->created_at,
            'updated_at' => $blog->updated_at,
            'tags' => $blog->tags->pluck('name')->toArray(), // ✅ Return tags as an array
            'admin' => $admin
        ], 'Blog details retrieved successfully');
    }


     /**
     * Store a new blog.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // ✅ Validate input including tags
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|string',
            'youtube_videos' => 'nullable|array',
            'youtube_videos.*' => 'url',
            'tags' => 'nullable|array', // Tags should be an array
            'tags.*' => 'string|max:50' // Each tag should be a string
        ]);

        // ✅ Start a database transaction
        DB::beginTransaction();

        try {
            // ✅ Create a blog post
            $blog = Blog::create([
                'title' => $request->title,
                'content' => $request->content,
                'image' => $request->image,
                'youtube_videos' => $request->youtube_videos ?? [],
                'admin_id' => Auth::id(),
                'status' => 'draft',
            ]);

            // ✅ Process Tags
            if ($request->has('tags')) {
                $tagIds = [];

                foreach ($request->tags as $tagName) {
                    $tag = Tag::firstOrCreate(['name' => $tagName]); // Find or create tag
                    $tagIds[] = $tag->id;
                }

                $blog->tags()->sync($tagIds); // Attach tags to blog
            }

            // ✅ Fetch admin details
            $admin = $blog->admin()->first(['uuid', 'name', 'email']);

            // ✅ Commit transaction
            DB::commit();

            return ApiResponse::sendResponse([
                'uuid' => $blog->uuid,
                'title' => $blog->title,
                'content' => $blog->content,
                'image' => $blog->image,
                'youtube_videos' => $blog->youtube_videos,
                'status' => $blog->status,
                'published_at' => $blog->published_at,
                'created_at' => $blog->created_at,
                'updated_at' => $blog->updated_at,
                'tags' => $blog->tags->pluck('name')->toArray(), // ✅ Return tag names
                'admin' => $admin
            ], 'Blog created successfully', 201);

        } catch (\Exception $e) {
            // ❌ Rollback on failure
            DB::rollBack();
            return ApiResponse::error('Failed to create blog', ['error' => $e->getMessage()], 500);
        }
    }
}
