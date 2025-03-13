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
use Exception;

class BlogController extends Controller
{
    public function getTopBlogsByEngagement()
    {
        $userId = auth()->id(); // Get the authenticated user ID

        // Fetch top 10 blogs based on views and likes
        $topBlogs = Blog::where('is_deleted', false)
            ->where('status', 'published')
            ->withCount('likes') // Count likes for sorting
            ->with([
                'user:id,uuid,name,email,avatar',
                'tags:id,uuid,name',
                'bookmarks' => function ($query) use ($userId) {
                    $query->where('user_id', $userId); // Filter bookmarks for the current user
                }
            ])
            ->orderByDesc('views')
            ->orderByDesc('likes_count')
            ->limit(10)
            ->get();

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
                'likes' => $blog->likes_count,
                'created_at' => $blog->created_at,
                'updated_at' => $blog->updated_at,
                'tags' => $blog->tags->map(fn($tag) => [
                    'uuid' => $tag->uuid,
                    'name' => $tag->name
                ]),
                'is_bookmarked' => $blog->bookmarks->isNotEmpty(),
                'user' => optional($blog->user)->only(['uuid', 'name', 'email', 'avatar'])
            ];
        });

        return ApiResponse::sendResponse($responseData, 'Top 10 blogs by engagement retrieved successfully');
    }

    // Method for admin to confirm and award the blog
    public function confirmAward(Request $request, $uuid)
    {
        $request->validate([
            'award_type' => 'required|in:best_content,most_viewed,most_liked',
            'award_rank' => 'required|in:1,2,3',
        ]);

        try {
            $blog = Blog::where('uuid', $uuid)->first();

            if (!$blog) {
                return ApiResponse::error('Blog not found.', [], 404);
            }

            if ($blog->is_awarded && $blog->award_rank === $request->award_rank) {
                return ApiResponse::error('This blog has already been awarded this rank.', [], 400);
            }

            // Ensure only admins can award
            if (!Auth::user() || !Auth::user()->hasRole('admin')) {
                return ApiResponse::error('Only admins can confirm awards.', [], 403);
            }

            // Check if this rank is already taken for this award type
            $existingAward = Blog::where('award_type', $request->award_type)
                ->where('award_rank', $request->award_rank)
                ->first();

            if ($existingAward) {
                return ApiResponse::error("Rank {$request->award_rank} has already been assigned for this award type.", [], 400);
            }

            $blog->update([
                'is_awarded' => true,
                'awarded_at' => now(),
                'awarded_by' => Auth::id(),
                'award_type' => $request->award_type,
                'award_rank' => $request->award_rank,
            ]);

            return ApiResponse::sendResponse([
                'uuid' => $blog->uuid,
                'title' => $blog->title,
                'is_awarded' => $blog->is_awarded,
                'awarded_at' => $blog->awarded_at,
                'awarded_by' => $blog->awardedBy?->only(['uuid', 'name', 'email']),
                'award_type' => $blog->award_type,
                'award_rank' => $blog->award_rank,
            ], "🏆 Blog has been awarded successfully as Rank {$blog->award_rank}!");
            
        } catch (Exception $e) {
            return ApiResponse::rollback('Failed to award the blog.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Load all tags.
     */
    public function getAllTags()
    {
        try {
            $tags = Tag::all(['uuid', 'name']); 

            return response()->json([
                'success' => true,
                'tags' => $tags
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load tags.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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


    public function getTopBlogs()
    {
        $userId = auth()->id(); // Get the authenticated user ID

        // Fetch blogs with top award ranks (1 to 3)
        $topBlogs = Blog::where('is_deleted', false)
            ->where('status', 'published')
            ->whereNotNull('award_rank') // Only fetch awarded blogs
            ->orderBy('award_rank') // Order by rank (1, 2, 3)
            ->with([
                'user:id,uuid,name,email,avatar',
                'tags:id,uuid,name',
                'awardedBy:id,uuid,name,email', // Include awarding admin
                'bookmarks' => function ($query) use ($userId) {
                    $query->where('user_id', $userId); // Only fetch bookmarks for the authenticated user
                }
            ])
            ->get();

        // If no blogs exist, return an empty response
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
                'tags' => $blog->tags->map(fn($tag) => [
                    'uuid' => $tag->uuid,
                    'name' => $tag->name
                ]),
                'is_bookmarked' => $blog->bookmarks->isNotEmpty(),
                'user' => optional($blog->user)->only(['uuid', 'name', 'email', 'avatar']),
                'award' => [
                    'type' => $blog->award_type,
                    'rank' => $blog->award_rank,
                    'awarded_at' => $blog->awarded_at,
                    'awarded_by' => optional($blog->awardedBy)->only(['uuid', 'name', 'email']),
                ]
            ];
        });

        return ApiResponse::sendResponse($responseData, 'Top awarded blogs retrieved successfully');
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


    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10);
        $userId = auth()->id(); // Get the authenticated user ID
        $tagUuids = $request->query('tags'); // Fetch tag UUIDs for filtering

        // Base query to fetch blogs
        $query = Blog::where('is_deleted', false)
            ->with(['user:id,uuid,name,email,avatar', 'tags:id,uuid,name', 'bookmarks' => function ($query) use ($userId) {
                $query->where('user_id', $userId); // Filter bookmarks for the current user
            }]);

        // Apply tag filtering if tags are provided
        if (!empty($tagUuids)) {
            $tagUuidArray = explode(',', $tagUuids); // Allow multiple tags in query (comma-separated)

            $query->whereHas('tags', function ($q) use ($tagUuidArray) {
                $q->whereIn('uuid', $tagUuidArray);
            });
        }

        // Paginate results
        $blogs = $query->paginate($perPage);

        if ($blogs->isEmpty()) {
            return ApiResponse::error('No blogs found', [], 404);
        }

        // Format the response data
        $responseData = $blogs->map(function ($blog) use ($userId) {
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
                'tags' => $blog->tags->map(fn($tag) => [
                    'uuid' => $tag->uuid,
                    'name' => $tag->name
                ]),
                'is_bookmark' => $blog->bookmarks->isNotEmpty(),
                'user' => [
                    'uuid' => $blog->user?->uuid,
                    'name' => $blog->user?->name,
                    'email' => $blog->user?->email,
                    'avatar' => $blog->user?->avatar
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
        // Find the blog by UUID with tags, likes, comments, and bookmarks
        $blog = Blog::where('uuid', $uuid)
            ->with(['tags', 'likes', 'comments.user', 'bookmarks' => function ($query) {
                $query->where('user_id', auth()->id());
            }])
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

        // Check if the authenticated user has bookmarked the blog
        $isBookmarked = auth()->check() 
            ? $blog->bookmarks->isNotEmpty() 
            : false;

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
            'is_bookmarked' => $isBookmarked, 
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
        // Check if the authenticated user is an admin
        if (Auth::user()->hasRole('admin')) {
            return ApiResponse::error('🚫 Admins are not allowed to create blogs.', [], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|string',
            'youtube_videos' => 'nullable|array',
            'youtube_videos.*' => 'url',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50'
        ]);

        DB::beginTransaction();

        try {
            $blog = Blog::create([
                'uuid' => Str::uuid(),
                'title' => $request->title,
                'content' => $request->content,
                'image' => $request->image,
                'youtube_videos' => $request->youtube_videos ?? [],
                'user_id' => Auth::id(),  
                'status' => 'draft',
            ]);

            if ($request->has('tags')) {
                $tagIds = [];
                foreach ($request->tags as $tagName) {
                    $tag = Tag::firstOrCreate(['name' => $tagName], [
                        'uuid' => Str::uuid() 
                    ]);
                    $tagIds[] = $tag->id;
                }
                $blog->tags()->sync($tagIds); 
            }

            $user = $blog->user()->first(['uuid', 'name', 'email']);

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
                'user' => $user
            ], '🎉 Blog created successfully!', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('🚨 Failed to create blog!', ['error' => $e->getMessage()], 500);
        }
    }

}
