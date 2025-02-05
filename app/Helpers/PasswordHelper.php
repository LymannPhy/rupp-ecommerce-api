<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Http\Responses\ApiResponse;

class PasswordHelper
{
    /**
     * Validate password change request.
     *
     * @param array $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public static function validatePasswordChange($data)
    {
        return Validator::make($data, [
            'current_password' => 'required|string|min:6',
            'new_password' => [
                'required',
                'string',
                'min:8',             
                'regex:/[A-Z]/',     
                'regex:/[a-z]/',    
                'regex:/[0-9]/',      
                'regex:/[@$!%*?&]/',  
                'confirmed'          
            ],
            'new_password_confirmation' => 'required|string|min:8'
        ], [
            'new_password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.'
        ]);
    }

    /**
     * Check password conditions before updating.
     *
     * @param object $user
     * @param string $currentPassword
     * @param string $newPassword
     * @return mixed
     */
    public static function checkPasswordConditions($user, $currentPassword, $newPassword)
    {
        // Verify the current password
        if (!Hash::check($currentPassword, $user->password)) {
            return ApiResponse::error('Incorrect password ❌', ['current_password' => 'The current password is incorrect.'], 400);
        }

        // Prevent setting the same password as before
        if (Hash::check($newPassword, $user->password)) {
            return ApiResponse::error('Invalid request ❌', ['new_password' => 'You cannot use your current password as the new password.'], 400);
        }

        return null; // No errors
    }
}
