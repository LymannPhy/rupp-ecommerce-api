<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Helpers\PaginationHelper;
use App\Models\BlogComment;
use App\Models\BlogLike;
use App\Models\Tag;
use Illuminate\Support\Str;

class BlogController extends Controller
{

    /**
     * Publish a blog by UUID.
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function publishBlog($uuid)
    {
        try {
            // 🔹 Find blog by UUID
            $blog = Blog::where('uuid', $uuid)->where('is_deleted', false)->first();

            if (!$blog) {
                return ApiResponse::error('Blog not found', [], 404);
            }

            // 🔹 Check if already published
            if ($blog->status === 'published') {
                return ApiResponse::error('Blog is already published', [], 400);
            }

            // 🔹 Update blog status to 'published' and set published_at timestamp
            $blog->update([
                'status' => 'published',
                'published_at' => now(),
            ]);

            return ApiResponse::sendResponse([
                'uuid' => $blog->uuid,
                'title' => $blog->title,
                'status' => $blog->status,
                'published_at' => $blog->published_at,
            ], 'Blog published successfully');

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to publish blog', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Like/Unlike a Blog Post using UUID.
     */
    public function likeBlog(Request $request, $uuid)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return ApiResponse::error('Unauthorized ❌', ['details' => 'You must be logged in to like a blog post.'], 401);
            }

            $blog = Blog::where('uuid', $uuid)->first();
            if (!$blog) {
                return ApiResponse::error('Blog Not Found 🚫', ['details' => 'The blog post you are trying to like does not exist.'], 404);
            }

            $existingLike = BlogLike::where('user_id', $user->id)->where('blog_id', $blog->id)->first();

            if ($existingLike) {
                // Unlike if already liked
                $existingLike->delete();

                return ApiResponse::sendResponse([
                    'likes_count' => $blog->likes()->count(),
                ], 'Blog unliked successfully ❌', 200);
            } else {
                // Like the blog
                BlogLike::create(['user_id' => $user->id, 'blog_id' => $blog->id]);

                return ApiResponse::sendResponse([
                    'likes_count' => $blog->likes()->count(),
                ], 'Blog liked successfully ❤️', 200);
            }
        } catch (\Exception $e) {
            return ApiResponse::error('Something went wrong 🤯', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Add a comment or reply to a blog using UUID.
     */
    public function commentOnBlog(Request $request, $uuid)
    {
        try {
            // Validate request
            $request->validate([
                'content' => 'required|string',
                'parent_uuid' => 'nullable|exists:blog_comments,uuid' 
            ]);

            $user = Auth::user();
            if (!$user) {
                return ApiResponse::error('Unauthorized ❌', ['details' => 'You must be logged in to comment on this blog.'], 401);
            }

            $blog = Blog::where('uuid', $uuid)->first();
            if (!$blog) {
                return ApiResponse::error('Blog Not Found 🚫', ['details' => 'The blog you are trying to comment on does not exist.'], 404);
            }

            // Convert parent UUID to ID if replying to another comment
            $parentComment = null;
            if ($request->parent_uuid) {
                $parentComment = BlogComment::where('uuid', $request->parent_uuid)->first();
                if (!$parentComment) {
                    return ApiResponse::error('Parent Comment Not Found 🗨️', ['details' => 'The comment you are trying to reply to does not exist.'], 404);
                }
            }

            // Create the comment
            $comment = BlogComment::create([
                'uuid' => Str::uuid(),
                'user_id' => $user->id,
                'blog_id' => $blog->id,
                'parent_id' => $parentComment ? $parentComment->id : null, // Support replies
                'content' => $request->content,
            ]);

            return ApiResponse::sendResponse([
                'comment' => [
                    'uuid' => $comment->uuid,
                    'user' => [
                        'uuid' => $user->uuid,
                        'name' => $user->name,
                        'avatar' => $user->avatar,
                    ],
                    'content' => $comment->content,
                    'created_at' => $comment->created_at,
                    'parent_uuid' => $parentComment ? $parentComment->uuid : null,
                ],
            ], 'Comment added successfully 📝', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Something went wrong 🤯', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Get all comments for a specific blog using UUID.
     */
    public function getBlogComments($uuid)
    {
        try {
            $blog = Blog::where('uuid', $uuid)->first();
            if (!$blog) {
                return ApiResponse::error('Blog Not Found 🚫', ['details' => 'The blog post you are trying to fetch comments for does not exist.'], 404);
            }

            // Fetch top-level comments (excluding replies)
            $comments = BlogComment::where('blog_id', $blog->id)
                ->whereNull('parent_id')
                ->orderBy('created_at', 'asc')
                ->with(['user:id,uuid,name,avatar', 'replies.user:id,uuid,name,avatar']) // Load user details & replies
                ->get();

            // Format comments with nested replies
            $formattedComments = $comments->map(function ($comment) {
                return $this->formatComment($comment);
            });

            return ApiResponse::sendResponse([
                'blog_uuid' => $blog->uuid,
                'total_comments' => $comments->count(),
                'comments' => $formattedComments,
            ], 'Blog comments retrieved successfully 🗨️', 200);
        } catch (\Exception $e) {
            return ApiResponse::error('Something went wrong 🤯', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Recursively format a comment with nested replies.
     */
    private function formatComment($comment)
    {
        return [
            'uuid' => $comment->uuid,
            'user' => [
                'uuid' => $comment->user->uuid ?? null,
                'name' => $comment->user->name ?? 'Unknown User',
                'avatar' => $comment->user->avatar ?? null,
            ],
            'content' => $comment->content,
            'created_at' => $comment->created_at,
            'replies' => $comment->replies->map(function ($reply) {
                return $this->formatComment($reply); 
            }),
        ];
    }


    /**
     * Delete a comment using UUID (Only owner or admin).
     */
    public function deleteComment($uuid)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return ApiResponse::error('Unauthorized ❌', ['details' => 'You must be logged in to delete a comment.'], 401);
            }

            $comment = BlogComment::where('uuid', $uuid)->first();
            if (!$comment) {
                return ApiResponse::error('Comment Not Found 🚫', ['details' => 'The comment you are trying to delete does not exist.'], 404);
            }

            // Ensure only the comment owner or an admin can delete it
            if ($comment->user_id !== $user->id && !$user->hasRole('admin')) {
                return ApiResponse::error('Forbidden ❌', ['details' => 'You do not have permission to delete this comment.'], 403);
            }

            $comment->delete();
            return ApiResponse::sendResponse(null, 'Comment deleted successfully 🗑️', 200);
        } catch (\Exception $e) {
            return ApiResponse::error('Something went wrong 🤯', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Get the top 10 blogs with the most views.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTopBlogs()
    {
        $topBlogs = Blog::where('is_deleted', false)
            ->where('status', 'published') 
            ->orderByDesc('views') 
            ->limit(10) 
            ->with(['admin:id,uuid,name,email,avatar'])
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
        $perPage = $request->query('per_page', 10);

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
                'tags' => $blog->tags->pluck('name')->toArray(),
                'user' => [
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
        // Find the blog by UUID with tags, likes, and comments
        $blog = Blog::where('uuid', $uuid)
            ->with(['tags', 'likes', 'comments.user'])
            ->first();

        if (!$blog) {
            return ApiResponse::error('Blog not found', [], 404);
        }

        // Increment views
        $blog->increment('views');

        // Fetch admin user details
        $admin = $blog->admin()->first(['uuid', 'name', 'email', 'avatar']);

        // Count total likes and comments
        $likesCount = $blog->likes()->count();
        $commentsCount = $blog->comments()->count();

        // Fetch the latest 5 comments with user details
        $latestComments = $blog->comments()
            ->with('user:id,name,avatar')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'user' => [
                        'id' => $comment->user->id,
                        'name' => $comment->user->name,
                        'avatar' => $comment->user->avatar,
                    ],
                    'created_at' => $comment->created_at,
                ];
            });

        // Check if the authenticated user has liked the blog
        $userLiked = auth()->check() 
            ? $blog->likes()->where('user_id', auth()->id())->exists() 
            : false;

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
            'tags' => $blog->tags->pluck('name')->toArray(),
            'user' => $admin,
            'likes_count' => $likesCount,
            'comments_count' => $commentsCount,
            'latest_comments' => $latestComments,
            'user_liked' => $userLiked,
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
            'tags' => 'nullable|array', 
            'tags.*' => 'string|max:50' 
        ]);

        // ✅ Start a database transaction
        DB::beginTransaction();

        try {
            // ✅ Create a blog post
            $blog = Blog::create([
                'uuid' => Str::uuid(),
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
                    // ✅ Ensure UUID is set when creating a new tag
                    $tag = Tag::firstOrCreate(['name' => $tagName], [
                        'uuid' => Str::uuid() 
                    ]);
                    $tagIds[] = $tag->id;
                }

                $blog->tags()->sync($tagIds); 
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
                'tags' => $blog->tags->pluck('name')->toArray(), 
                'user' => $admin
            ], '🎉 Blog created successfully!', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('🚨 Failed to create blog!', ['error' => $e->getMessage()], 500);
        }
    }

}
