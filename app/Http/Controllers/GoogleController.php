<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;
use Exception;
use Google_Client;
use Illuminate\Support\Facades\Http;

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
            $code = $request->input('code');
            if (!$code) {
                return response()->json(['error' => 'Missing authorization code'], 400);
            }

            $tokenUrl = 'https://oauth2.googleapis.com/token';
            $redirectUri = config('services.google.redirect'); 

            $response = Http::asForm()->post($tokenUrl, [
                'code' => $code,
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Token exchange failed', 'details' => $response->body()], 500);
            }

            $tokenData = $response->json();

            // ✅ Verify ID Token
            $client = new Google_Client(['client_id' => config('services.google.client_id')]);
            $payload = $client->verifyIdToken($tokenData['id_token']);

            if (!$payload) {
                return response()->json(['error' => 'Invalid ID token'], 400);
            }

            $googleId = $payload['sub'];
            $email = $payload['email'];
            $name = $payload['name'];

            // ✅ Check or create user
            $user = User::where('google_id', $googleId)->orWhere('email', $email)->first();

            if (!$user) {
                $username = explode('@', $email)[0];
                $base = $username;
                $counter = 1;
                while (User::where('name', $username)->exists()) {
                    $username = $base . $counter;
                    $counter++;
                }

                $user = User::create([
                    'name'        => $username,
                    'email'       => $email,
                    'google_id'   => $googleId,
                    'password'    => Hash::make('123456dummy'),
                    'is_verified' => true,
                ]);
            }

            // ✅ Generate JWT
            $accessToken = JWTAuth::fromUser($user);
            $refreshToken = JWTAuth::claims(['refresh' => true])->fromUser($user);

            return response()->json([
                'access_token'  => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type'    => 'Bearer',
                'expires_in'    => config('jwt.ttl') * 60,
                'user'          => [
                    'id'       => $user->id,
                    'name'     => $user->name,
                    'email'    => $user->email,
                    'google_id'=> $user->google_id,
                ],
            ]);

        } catch (Exception $e) {
            return response()->json(['error' => 'Google login failed', 'details' => $e->getMessage()], 500);
        }
    }
}