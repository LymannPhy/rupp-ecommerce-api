<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Bookmark;
use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookmarkController extends Controller
{
    /**
     * Get all bookmarks of the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $userId = Auth::id();

        // Fetch all bookmarks with blog details
        $bookmarks = Bookmark::where('user_id', $userId)
            ->with(['blog:id,uuid,title,image,status,published_at'])
            ->get();

        if ($bookmarks->isEmpty()) {
            return ApiResponse::error('No bookmarks found', [], 404);
        }

        // Format response
        $responseData = $bookmarks->map(function ($bookmark) {
            return [
                'uuid' => $bookmark->uuid,
                'blog' => [
                    'uuid' => $bookmark->blog->uuid,
                    'title' => $bookmark->blog->title,
                    'image' => $bookmark->blog->image,
                    'status' => $bookmark->blog->status,
                    'published_at' => $bookmark->blog->published_at,
                ],
                'created_at' => $bookmark->created_at,
            ];
        });

        return ApiResponse::sendResponse($responseData, 'User bookmarks retrieved successfully');
    }

    /**
     * Remove a bookmark (Unbookmark a blog).
     *
     * @param string $uuid The UUID of the bookmark.
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($uuid)
    {
        $userId = Auth::id();

        // Find the bookmark by UUID and user_id
        $bookmark = Bookmark::where('uuid', $uuid)
            ->where('user_id', $userId)
            ->first();

        if (!$bookmark) {
            return ApiResponse::error('Bookmark not found', [], 404);
        }

        // Delete the bookmark
        $bookmark->delete();

        return ApiResponse::sendResponse([], 'Blog unbookmarked successfully', 200);
    }


    /**
     * Add a blog to the user's bookmarks using UUID.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'blog_uuid' => 'required|exists:blogs,uuid',
        ]);

        $userId = Auth::id();

        // Find the blog by UUID
        $blog = Blog::where('uuid', $request->blog_uuid)->firstOrFail();

        // Check if already bookmarked
        $existingBookmark = Bookmark::where('user_id', $userId)
            ->where('blog_id', $blog->id)
            ->first();

        if ($existingBookmark) {
            return ApiResponse::error('Blog is already bookmarked', [], 400);
        }

        // Create new bookmark
        $bookmark = Bookmark::create([
            'user_id' => $userId,
            'blog_id' => $blog->id, 
        ]);

        return ApiResponse::sendResponse([
            'bookmark_uuid' => $bookmark->uuid ?? null,
            'blog_uuid' => $blog->uuid,
        ], 'Blog bookmarked successfully', 201);
    }
}
