<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Helpers\PasswordHelper;


class UserController extends Controller
{
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
                return $passwordCheck; // Return error response if conditions fail
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
     * Load all users except admins (Admins Only).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllUsers()
    {
        try {
            $user = auth()->user();

            // Ensure only admin users can access this method
            if (!$user->hasRole('admin')) {
                return ApiResponse::throw('Forbidden', ['error' => 'You are not authorized to access this resource'], 403);
            }

            // Fetch users excluding those with the 'admin' role
            $users = User::whereDoesntHave('roles', function ($query) {
                $query->where('name', 'admin');
            })->get();

            return ApiResponse::sendResponse(UserResource::collection($users), 'Users retrieved successfully.');
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
