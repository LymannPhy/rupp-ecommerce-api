<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Hash;
use Exception;

class GoogleController extends Controller
{
    /**
     * Redirect the user to Google's OAuth page (stateless).
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Handle the callback from Google in stateless mode and generate JWT tokens.
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::where('google_id', $googleUser->id)->first();

            if (!$user) {
                $user = User::create([
                    'name'        => $googleUser->name,
                    'email'       => $googleUser->email,
                    'google_id'   => $googleUser->id,
                    'password'    => Hash::make('123456dummy'),
                    'is_verified' => 1,  
                ]);

            } else {
                $user->update(['is_verified' => 1]);
            }

            if (!$token = JWTAuth::fromUser($user)) {
                return ApiResponse::error('Could not create token', [], 500);
            }

            $ttl = config('jwt.ttl');
            $expires_in = $ttl * 60;

            $refresh_token = JWTAuth::claims(['refresh' => true])->fromUser($user);

            $userData = [
                'username'    => $user->name,
                'email'       => $user->email,
                'avatar'      => $user->avatar ?? null, 
                'role'        => $user->roles()->pluck('name')->first() ?? 'user', 
                'is_verified' => $user->is_verified,     
                'is_blocked'  => $user->is_blocked,      
            ];

            $tokenData = [
                'access_token'  => $token,
                'refresh_token' => $refresh_token,
                'token_type'    => 'Bearer',
                'expires_in'    => $expires_in,
                'user'          => $userData,
            ];

            return ApiResponse::sendResponse(
                $tokenData,
                "Welcome back, {$user->name}! 🎉 You're all set to conquer the world. 🌟",
                200
            );

        } catch (JWTException $e) {
            // Handle JWT token creation failures
            return ApiResponse::error(
                'Could not create token',
                ['exception' => $e->getMessage()],
                500
            );
        } catch (Exception $e) {
            return ApiResponse::error(
                'Google login failed',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}