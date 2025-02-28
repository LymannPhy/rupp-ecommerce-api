<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Handle unauthenticated requests and return a JSON response.
     */
    protected function unauthenticated($request, AuthenticationException $exception): JsonResponse
    {
        return response()->json([
            'code' => Response::HTTP_UNAUTHORIZED,
            'message' => 'Unauthorized ❌',
            'date' => now()->toDateTimeString(),
            'errors' => [
                'details' => "Uh-oh! 🚨 It looks like you’re not logged in. Maybe you mistyped your password 🤔, or maybe you're secretly a hacker trying to break in? 😱 Either way, you need to authenticate first! 🔐 Please log in and try again. We've got cookies 🍪 (but only for logged-in users 😉)."
            ],
        ], Response::HTTP_UNAUTHORIZED);
    }


    /**
     * Force all exceptions to return JSON for API requests.
     */
    public function render($request, Throwable $exception): JsonResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            // Handle Validation Errors
            if ($exception instanceof \Illuminate\Validation\ValidationException) {
                return response()->json([
                    'status' => 'error',
                    'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                    'message' => 'Validation failed',
                    'errors' => $exception->errors(), 
                    'date' => now()->toDateTimeString(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Handle General Exceptions
            return response()->json([
                'status' => 'error',
                'code' => method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500,
                'message' => 'Something went wrong', 
                'errors' => [
                    'details' => $exception->getMessage(),
                ],
                'date' => now()->toDateTimeString(),
            ], method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500);
        }

        // ✅ Ensure non-JSON responses are also wrapped in JSON
        $response = parent::render($request, $exception);
        
        return response()->json([
            'status' => 'error',
            'code' => $response->getStatusCode(),
            'message' => 'Unexpected error',
            'errors' => [
                'details' => $exception->getMessage(),
            ],
            'date' => now()->toDateTimeString(),
        ], $response->getStatusCode());
    }


}
