<?php

namespace App\Http\Controllers;

use App\Models\LinkedSocialAccount;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Str;

class SocialController extends Controller
{
    public function redirectToProvider($provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    public function handleProviderCallback($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid authentication'], 401);
        }

        // Check if social account exists
        $account = LinkedSocialAccount::where('provider_id', $socialUser->id)
            ->where('provider_name', $provider)
            ->first();

        if ($account) {
            return $this->issueToken($account->user);
        } 

        // Check if user exists by email
        $user = User::where('email', $socialUser->getEmail())->first();

        if (!$user) {
            // Extract names properly for different providers
            $firstName = '';
            $lastName = '';

            if ($provider === 'google') {
                $firstName = $socialUser->user['given_name'] ?? '';
                $lastName = $socialUser->user['family_name'] ?? '';
            } else {
                $firstName = $socialUser->getName(); 
            }

            // Create new user
            $user = User::create([
                'uuid' => Str::uuid(),
                'name' => trim("$firstName $lastName"),
                'email' => $socialUser->getEmail(),
                'password' => Hash::make(Str::random(16)),
                'avatar' => $socialUser->getAvatar(), 
                'is_verified' => true, 
            ]);
        }

        // Link social account
        $socialAccount = LinkedSocialAccount::create([
            'user_id' => $user->id,
            'provider_id' => $socialUser->id,
            'provider_name' => $provider,
        ]);

        return $this->issueToken($user);
    }

    private function issueToken(User $user)
    {
        $token = $user->createToken('socialLogin')->accessToken;

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token
        ]);
    }
}
