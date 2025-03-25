<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Http\Resources\AuthResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
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
     * Handle user login and return JWT tokens along with user information.
     *
     * This method authenticates the user using email and password, generates an access token,
     * refresh token, and includes user details in the response. It uses the ApiResponse class
     * for standardized API responses.
     *
     * @param Request $request The HTTP request containing 'email' and 'password'.
     *
     * @return \Illuminate\Http\JsonResponse
     * - **200 OK**: On successful authentication, returns:
     *   - `access_token`: The JWT access token for authenticated requests.
     *   - `refresh_token`: A token to refresh the access token upon expiry.
     *   - `token_type`: Typically 'Bearer'.
     *   - `expires_in`: Token expiry time in seconds.
     *   - `user`: Authenticated user's information (username, email, avatar, role).
     *
     * - **401 Unauthorized**: If credentials are invalid.
     * - **500 Internal Server Error**: If token creation fails due to a server error.
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        try {
            // Attempt to authenticate the user with provided credentials
            if (! $token = JWTAuth::attempt($credentials)) {
                return ApiResponse::error('Invalid credentials', [], 401);
            }

            // Get the authenticated user
            $user = auth()->user();

            // Token Time To Live (in minutes), converted to seconds for expires_in
            $ttl = config('jwt.ttl');
            $expires_in = $ttl * 60;

            // Generate refresh token with custom claim
            $refresh_token = JWTAuth::claims(['refresh' => true])->fromUser($user);

            // Prepare user data to return
            $userData = [
                'username' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'role' => $user->roles()->pluck('name')->first(), // Assuming a single role
            ];

            // Prepare token data
            $tokenData = [
                'access_token' => $token,
                'refresh_token' => $refresh_token,
                'token_type' => 'Bearer',
                'expires_in' => $expires_in,
                'user' => $userData
            ];

            // Return success response using ApiResponse with a personalized, fun message
            return ApiResponse::sendResponse($tokenData, "Welcome back, {$user->name}! 🎉 You're all set to conquer the world. 🌟", 200);


        } catch (JWTException $e) {
            // Handle token creation failure
            return ApiResponse::error('Could not create token', ['exception' => $e->getMessage()], 500);
        }
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ];
    }


    /**
     * Refreshes the user's access token using a valid refresh token.
     * 
     * This method validates the provided refresh token, generates a new access token 
     * and refresh token, and returns them along with the user's details 
     * (name, email, avatar, role) and the expiration time.
     * 
     * @param \Illuminate\Http\Request $request The HTTP request containing the refresh token.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh(Request $request)
    {
        $refreshToken = $request->input('refresh_token');

        // Validate if refresh token is provided
        if (!$refreshToken) {
            return ApiResponse::error('Refresh token is required.', ['error' => 'No refresh token provided.'], 400);
        }

        try {
            // Parse and validate the refresh token
            $newToken = JWTAuth::setToken($refreshToken)->refresh();

            // Get the user from the refresh token
            $user = JWTAuth::setToken($refreshToken)->toUser();

            if (!$user) {
                return ApiResponse::error('Unauthorized.', ['error' => 'Invalid refresh token.'], 401);
            }

            return ApiResponse::sendResponse([
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar ?? null,
                    'role' => $user->roles()->pluck('name')->first() ?? 'user',
                ],
                'access_token' => $newToken,
                'refresh_token' => JWTAuth::fromUser($user), // Issue new refresh token
                'token_type' => 'bearer',
                'expires_in' => auth()->factory()->getTTL() * 60,
            ], 'Your access token has been refreshed! 🔄');

        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return ApiResponse::error('Invalid refresh token.', ['error' => $e->getMessage()], 401);
        } catch (\Exception $e) {
            return ApiResponse::error('Could not refresh token.', ['error' => $e->getMessage()], 500);
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
                return ApiResponse::error('Registration failed', ['email' => 'Email is already registered'], 409);
            }

            // Generate verification code
            $verificationCode = rand(100000, 999999);
            $verificationExpiration = now()->addMinutes(10);

            // Create user
            $user = User::create([
                'uuid' => Str::uuid()->toString(),
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
                'verification_code' => $verificationCode,
                'verification_code_expiration' => $verificationExpiration,
            ]);

            // Assign "user" role
            $userRole = Role::where('name', 'user')->first();
            if (!$userRole) {
                $userRole = Role::create(['name' => 'user']);
            }
            $user->roles()->sync([$userRole->id]);

            // Send verification email
            Mail::to($user->email)->send(new VerificationCodeMail($user->name, $verificationCode));

            DB::commit();

            return ApiResponse::sendResponse([
                'user' => new AuthResource($user),
                'token_type' => 'Bearer',
            ], 'User registered successfully. Please check your email for the verification code.', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('User registration failed', ['error' => $e->getMessage()], 500);
        }
    }

}