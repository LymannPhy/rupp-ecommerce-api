<?php

namespace App\Http\Controllers;

use App\Helpers\PaginationHelper;
use App\Models\User;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Helpers\PasswordHelper;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;


class UserController extends Controller
{
    /**
     * Toggle the block status of a user by UUID (Admin Only).
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleBlockUserByUuid(string $uuid)
    {
        try {
            // Ensure the authenticated user is an admin
            $user = auth()->user();

            if (!$user || !$user->hasRole('admin')) {
                return ApiResponse::error('Unauthorized ❌', ['error' => 'Only admin can block/unblock users.'], 403);
            }

            // Find the user by UUID
            $userToToggle = User::where('uuid', $uuid)->first();

            if (!$userToToggle) {
                return ApiResponse::error('User not found ❌', ['error' => 'No user found with this UUID'], 404);
            }

            // Toggle the user's block status
            $userToToggle->is_blocked = !$userToToggle->is_blocked;
            $userToToggle->save();

            $status = $userToToggle->is_blocked ? 'blocked' : 'unblocked';

            return ApiResponse::sendResponse([], "User has been $status successfully ✅");
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to toggle block status 🔥', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Delete a user by UUID.
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyByUuid(string $uuid)
    {
        // Check if the authenticated user is an admin
        $user = Auth::user();

        if (!$user || !$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized. Only admin can delete users.'], 403);
        }

        // Attempt to delete the user by UUID
        $deleted = User::deleteByUuid($uuid);

        if ($deleted) {
            return response()->json(['message' => 'User deleted successfully.'], 200);
        }

        return response()->json(['message' => 'User not found.'], 404);
    }


    /**
     * Partially update user profile information.
     *
     * This method allows users to update their profile details selectively.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return ApiResponse::error('Unauthorized ❌', ['error' => 'User not authenticated'], 401);
            }

            // Validate request data
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'avatar' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:500',
                'phone_number' => 'nullable|string',
                'bio' => 'nullable|string|max:1000',
                'date_of_birth' => 'nullable|date|before:today',
            ], [
                'date_of_birth.before' => 'The date of birth must be a past date.',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation Error ❌', $validator->errors()->toArray(), 422);
            }

            // Update only provided fields
            $user->update($request->only([
                'name',
                'avatar',
                'address',
                'phone_number',
                'bio',
                'date_of_birth'
            ]));

            return ApiResponse::sendResponse([
                'uuid' => $user->uuid,
                'name' => $user->name,
                'avatar' => $user->avatar,
                'address' => $user->address,
                'phone_number' => $user->phone_number,
                'bio' => $user->bio,
                'date_of_birth' => $user->date_of_birth,
                'updated_at' => now(),
            ], 'Profile updated successfully!');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update profile 🔥', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Change the authenticated user's password.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return ApiResponse::error('Unauthorized ❌', ['error' => 'User not authenticated'], 401);
            }

            // Validate request data using helper
            $validator = PasswordHelper::validatePasswordChange($request->all());

            if ($validator->fails()) {
                return ApiResponse::error('Validation Error ❌', $validator->errors()->toArray(), 422);
            }

            // Check password conditions before updating
            $passwordCheck = PasswordHelper::checkPasswordConditions($user, $request->current_password, $request->new_password);
            if ($passwordCheck) {
                return $passwordCheck; 
            }

            // Update and hash the new password
            $user->update([
                'password' => Hash::make($request->new_password),
            ]);

            return ApiResponse::sendResponse([], 'Password changed successfully! Please use your new password to log in next time.');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to change password 🔥', ['error' => $e->getMessage()], 500);
        }
    }

   /**
     * Load the currently authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCurrentUser()
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return ApiResponse::error('Unauthorized ❌', ['error' => 'User not authenticated'], 401);
            }

            return ApiResponse::sendResponse(new UserResource($user), 'Current user retrieved successfully ✅');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve user 🔥', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Load all users except admins (Admins Only) with pagination, search, and filter.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllUsers(Request $request)
    {
        try {
            $user = auth()->user();

            // Ensure only admin users can access this method
            if (!$user->hasRole('admin')) {
                return ApiResponse::throw('Forbidden', ['error' => 'You are not authorized to access this resource'], 403);
            }

            // Define the base query for users excluding the 'admin' role
            $query = User::whereDoesntHave('roles', function ($query) {
                $query->where('name', 'admin');
            });

            // Apply search filters for username and email
            if ($request->has('search') && $request->search) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('username', 'LIKE', "%$searchTerm%")
                    ->orWhere('email', 'LIKE', "%$searchTerm%");
                });
            }

            // Apply filters for 'is_blocked' if provided
            if ($request->has('is_blocked')) {
                $query->where('is_blocked', $request->is_blocked);
            }

            // Apply sorting by 'created_at' (either ascending or descending)
            $sortDirection = $request->get('sort_direction', 'desc'); // Default to descending order
            $query->orderBy('created_at', $sortDirection);

            // Apply pagination
            $perPage = $request->get('per_page', 15); // Default to 15 items per page
            $users = $query->paginate($perPage);

            // Format the response using the PaginationHelper
            $data = UserResource::collection($users);  // Transform the paginated results

            // Use the PaginationHelper to format the response
            $formattedResponse = PaginationHelper::formatPagination($users, $data);

            return ApiResponse::sendResponse($formattedResponse, 'Users retrieved successfully.');
            
        } catch (\Exception $e) {
            return ApiResponse::throw('Failed to retrieve users', ['error' => $e->getMessage()], 500);
        }
    }



    /**
     * Load user by UUID.
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserByUuid($uuid)
    {
        try {
            $user = User::where('uuid', $uuid)->first();

            if (!$user) {
                return ApiResponse::throw('User not found', ['uuid' => 'No user found with this UUID'], 404);
            }

            return ApiResponse::sendResponse(new UserResource($user), 'User retrieved successfully.');
        } catch (\Exception $e) {
            return ApiResponse::throw('Failed to retrieve user', ['error' => $e->getMessage()], 500);
        }
    }
}
