<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Http\Resources\AuthResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationCodeMail;
use App\Mail\ResetPasswordMail;
use Illuminate\Support\Str;
use App\Models\Role;

class AuthController extends Controller
{

    /**
     * Log out the authenticated user by revoking tokens.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        try {
            // Get authenticated user
            $user = auth()->user();

            if (!$user) {
                return ApiResponse::throw('Unauthorized', ['error' => 'No authenticated user found'], 401);
            }

            // Revoke all tokens
            $user->tokens()->delete();

            return ApiResponse::sendResponse([], 'Logout successful.');
        } catch (\Exception $e) {
            return ApiResponse::rollback('Logout failed', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Reset user password using the reset code.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
                'reset_code' => 'required|string',
                'new_password' => 'required|string|min:6|confirmed',
            ]);

            $user = User::where('email', $request->email)
                        ->where('reset_password_code', $request->reset_code)
                        ->first();

            // Check if reset code exists and is not expired
            if (!$user) {
                throw new \Exception('Invalid reset code.');
            }

            if (now()->greaterThan($user->reset_password_code_expiration)) {
                throw new \Exception('Reset code has expired.');
            }

            // Update password
            $user->update([
                'password' => bcrypt($request->new_password),
                'reset_password_code' => null,
                'reset_password_code_expiration' => null,
            ]);

            return ApiResponse::sendResponse([], 'Password reset successfully.');
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => []
            ], 400);
        }
    }



    /**
     * Request a password reset by sending a reset code via email.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestPasswordReset(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        // Generate a new password reset code and expiration time
        $resetCode = rand(100000, 999999);
        $resetCodeExpiration = now()->addMinutes(15); // Code expires in 15 minutes

        // Update user with reset code
        $user->update([
            'reset_password_code' => $resetCode,
            'reset_password_code_expiration' => $resetCodeExpiration,
        ]);

        // Send reset code via email
        Mail::to($user->email)->send(new ResetPasswordMail($user->name, $resetCode));

        return ApiResponse::sendResponse([], 'Password reset code sent successfully. Please check your email.');
    }


    /**
     * Verify user email using the verification code.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'verification_code' => 'required|string',
        ]);

        // Retrieve the user by email
        $user = User::where('email', $request->email)->first();

        // If user not found, return an error
        if (!$user) {
            return ApiResponse::throw('User not found.', [], 404);
        }

        // Check if user is already verified
        if ($user->is_verified) {
            return ApiResponse::sendResponse([], 'User is already verified.');
        }

        // Check if the verification code exists and is not expired
        if ($user->verification_code !== $request->verification_code || now()->greaterThan($user->verification_code_expiration)) {
            return ApiResponse::throw('Invalid or expired verification code.', [], 400);
        }

        // Mark user as verified
        $user->update([
            'is_verified' => true,
            'verification_code' => null,
            'verification_code_expiration' => null,
        ]);

        return ApiResponse::sendResponse(new AuthResource($user), 'Email verified successfully');
    }


    
    /**
     * Resend a new verification code to the user's email.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendVerificationCode(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            $user = User::where('email', $request->email)->first();

            // Check if the user is already verified
            if ($user->is_verified) {
                return ApiResponse::sendResponse([], 'User already verified.', 400);
            }

            // Generate a new verification code and expiration time
            $verificationCode = rand(100000, 999999);
            $verificationExpiration = now()->addMinutes(10); // Expires in 10 minutes

            // Update the user's verification code
            $user->update([
                'verification_code' => $verificationCode,
                'verification_code_expiration' => $verificationExpiration,
            ]);

            // Send the new verification code via email
            Mail::to($user->email)->send(new VerificationCodeMail($user->name, $verificationCode));

            return ApiResponse::sendResponse([], 'Verification code resent successfully. Please check your email.');
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to resend verification code',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Handle user login and generate access & refresh tokens.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        try {
            // Validate the login request
            $credentials = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string|min:6',
            ]);

            // Attempt authentication
            if (!auth()->attempt($credentials)) {
                return ApiResponse::throw('Unauthorized', ['error' => 'Invalid email or password'], 401);
            }

            // Retrieve authenticated user
            $user = auth()->user();

            // Check if the user is blocked
            if ($user->is_blocked) {
                return ApiResponse::throw('Login failed', ['error' => 'Your account has been blocked.'], 403);
            }

            // Check if the email is verified
            if (!$user->is_verified) {
                return ApiResponse::throw('Login failed', ['error' => 'Your email is not verified.'], 403);
            }

            // Retrieve the authenticated user
            $user = auth()->user();

            // Generate Access Token (Short-lived)
            $accessToken = $user->createToken('access_token', ['*'], now()->addMinutes(30))->plainTextToken;

            // Generate Refresh Token (Longer-lived)
            $refreshToken = $user->createToken('refresh_token', ['refresh'], now()->addDays(7))->plainTextToken;

            return ApiResponse::sendResponse([
                'user' => new AuthResource($user),
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
            ], 'Login successful');
        } catch (ValidationException $e) {
            return ApiResponse::throw('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::rollback('Login failed', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Register a new user and send a verification code via email.
     *
     * @param RegisterRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(RegisterRequest $request)
    {
        DB::beginTransaction();

        try {
            // Retrieve validated data
            $validated = $request->validated();

            // Check if email is already registered
            if (User::where('email', $validated['email'])->exists()) {
                return ApiResponse::throw('Registration failed', ['email' => 'Email is already registered'], 409);
            }

            // Generate verification code and expiration time
            $verificationCode = rand(100000, 999999);
            $verificationExpiration = now()->addMinutes(10); // Expires in 10 minutes

            // Create user
            $user = User::create([
                'uuid' => Str::uuid()->toString(),
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
                'verification_code' => $verificationCode,
                'verification_code_expiration' => $verificationExpiration,
            ]);

            // Assign default "user" role
            $userRole = Role::where('name', 'user')->first();

            if (!$userRole) {
                $userRole = Role::create(['name' => 'user']);
            }

            $user->roles()->sync([$userRole->id]); // Attach "user" role

            // Send verification code via email
            Mail::to($user->email)->send(new VerificationCodeMail($user->name, $verificationCode));

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'User registered successfully. Please check your email for the verification code.',
                'data' => [
                    'user' => new AuthResource($user),
                    'token_type' => 'Bearer',
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'User registration failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}