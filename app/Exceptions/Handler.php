<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Throwable;
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
     * Handle unauthenticated requests and return a custom JSON response.
     *
     * @param Request $request
     * @param AuthenticationException $exception
     * @return Response
     */
    protected function unauthenticated($request, AuthenticationException $exception): Response
    {
        return response()->json([
            'status' => 'error',
            'code' => Response::HTTP_UNAUTHORIZED,
            'message' => 'Unauthorized ❌',
            'icon' => '🚫',
            'date' => now()->toDateTimeString(),
            'errors' => ['error' => 'User not authenticated'],
        ], Response::HTTP_UNAUTHORIZED);
    }
}
