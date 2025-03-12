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
     * Get all bookmarks of the authenticated user with blog tags, views, and likes.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $userId = Auth::id();

        // Fetch all bookmarks with blog details, tags, views, and likes
        $bookmarks = Bookmark::where('user_id', $userId)
            ->with(['blog' => function ($query) {
                $query->select('id', 'uuid', 'title', 'image', 'status', 'published_at', 'views')
                    ->with(['tags:id,name', 'likes']);
            }])
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
                    'views' => $bookmark->blog->views,
                    'like_count' => $bookmark->blog->likes->count(),
                    'tags' => $bookmark->blog->tags->pluck('name'),
                ],
                'created_at' => $bookmark->created_at,
            ];
        });

        return ApiResponse::sendResponse($responseData, 'User bookmarks retrieved successfully');
    }

    /**
     * Toggle bookmark status for a blog (bookmark or unbookmark).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleBookmark(Request $request)
    {
        $request->validate([
            'blog_uuid' => 'required|exists:blogs,uuid',
        ]);

        $userId = Auth::id();

        // Find the blog by UUID
        $blog = Blog::where('uuid', $request->blog_uuid)->firstOrFail();

        // Check if the blog is already bookmarked by the user
        $existingBookmark = Bookmark::where('user_id', $userId)
            ->where('blog_id', $blog->id)
            ->first();

        if ($existingBookmark) {
            // If bookmarked, unbookmark it
            $existingBookmark->delete();

            return ApiResponse::sendResponse([
                'blog_uuid' => $blog->uuid,
                'is_bookmarked' => false
            ], 'Blog unbookmarked successfully', 200);
        } else {
            // If not bookmarked, create a new bookmark
            $bookmark = Bookmark::create([
                'user_id' => $userId,
                'blog_id' => $blog->id,
            ]);

            return ApiResponse::sendResponse([
                'bookmark_uuid' => $bookmark->uuid ?? null,
                'blog_uuid' => $blog->uuid,
                'is_bookmarked' => true
            ], 'Blog bookmarked successfully', 201);
        }
    }

}
