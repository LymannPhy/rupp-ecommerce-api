<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Helpers\PaginationHelper;

class FeedbackController extends Controller
{
    /**
     * Delete user feedback.
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteFeedback($uuid)
    {
        // Find the feedback by UUID
        $feedback = Feedback::where('uuid', $uuid)->first();

        if (!$feedback) {
            return ApiResponse::error('Feedback not found', [], 404);
        }

        // Delete the feedback
        $feedback->delete();

        return ApiResponse::sendResponse([], 'Feedback deleted successfully');
    }


    /**
     * Update the status of feedback from 'pending' to 'promoted' or vice versa.
     *
     * @param Request $request
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateFeedbackStatus(Request $request, $uuid)
    {
        // Validate the status parameter
        $validated = $request->validate([
            'status' => 'required|in:pending,promoted', // Ensure status is either 'pending' or 'promoted'
        ]);

        // Find the feedback by UUID
        $feedback = Feedback::where('uuid', $uuid)->first();

        if (!$feedback) {
            return ApiResponse::error('Feedback not found', [], 404);
        }

        // Update the status of the feedback
        $feedback->status = $validated['status'];
        $feedback->save();

        return ApiResponse::sendResponse([], 'Feedback status updated successfully');
    }


    /**
     * Retrieve all promoted user feedbacks (Admin access only).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPromotedFeedbacks(Request $request)
    {
        try {
            // Fetch all promoted feedbacks (those marked as promoted)
            $feedbacks = Feedback::where('status', 'promoted') // Filter by promoted status
                ->with('user:id,name,avatar') // Fetch associated user data (name, avatar)
                ->get(); // Get all promoted feedbacks without pagination

            // Format feedback data for response
            $formattedFeedbacks = $feedbacks->map(function ($feedback) {
                return [
                    'uuid' => $feedback->uuid,
                    'username' => $feedback->user->name,
                    'avatar' => $feedback->user->avatar ?? null, // If avatar exists in the user model
                    'message' => $feedback->message,
                    'type' => $feedback->type,
                    'status' => $feedback->status,
                    'created_at' => $feedback->created_at->toDateTimeString(),
                ];
            });

            return ApiResponse::sendResponse($formattedFeedbacks, 'All promoted feedbacks retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve promoted feedbacks', ['error' => $e->getMessage()], 500);
        }
    }


     /**
     * Retrieve all user feedback with pagination (Admin access only).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllFeedbacks(Request $request)
    {
        try {
            // Fetch all feedbacks with associated user data (name, avatar), paginated
            $feedbacks = Feedback::with('user:id,name,avatar')
                ->paginate(10); // Adjust the pagination size as needed (10 in this example)

            // Format feedback data for response
            $formattedFeedbacks = $feedbacks->map(function ($feedback) {
                return [
                    'uuid' => $feedback->uuid,
                    'username' => $feedback->user->name,
                    'avatar' => $feedback->user->avatar ?? null, // If avatar exists in the user model
                    'message' => $feedback->message,
                    'type' => $feedback->type,
                    'status' => $feedback->status ?? 'Pending', // Default status to Pending if null
                    'created_at' => $feedback->created_at->toDateTimeString(),
                ];
            });

            // Use helper function to format pagination response
            $paginationResponse = PaginationHelper::formatPagination($feedbacks, $formattedFeedbacks);

            return ApiResponse::sendResponse($paginationResponse, 'All user feedbacks retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve user feedbacks', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Store user feedback with validation.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeFeedback(Request $request)
    {
        // Validate the incoming request
        $validated = $request->validate([
            'message' => 'required|string|max:1000'
        ]);

        try {
            // Create the feedback entry
            $feedback = Feedback::create([
                'user_id' => Auth::id(), 
                'message' => $validated['message'],
            ]);

            // Return a success response
            return ApiResponse::sendResponse([
                'uuid' => $feedback->uuid,
                'message' => $feedback->message,
            ], 'Feedback submitted successfully!');
        } catch (\Exception $e) {
            // Handle any errors and rollback
            return ApiResponse::error('Failed to submit feedback', ['error' => $e->getMessage()], 500);
        }
    }
}
