<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Exception;

class ApiResponse
{
    /**
     * Send an error response without throwing an exception.
     *
     * @param string $message
     * @param array $errors
     * @param int $statusCode
     * @return JsonResponse
     */
    public static function error(string $message = 'Error', array $errors = [], int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'code' => $statusCode,
            'message' => $message . ' ❌',
            'icon' => '🚫',
            'date' => now()->toDateTimeString(),
            'errors' => $errors
        ], $statusCode);
    }
    

    /**
     * Send a standard API success response.
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @return JsonResponse
     */
    public static function sendResponse($data = [], string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    /**
     * Send an error response and optionally throw an exception.
     *
     * @param string $message
     * @param array $errors
     * @param int $statusCode
     * @throws Exception
     * @return JsonResponse
     */
    public static function throw(string $message = 'Error', array $errors = [], int $statusCode = 400): JsonResponse
    {
        throw new Exception(json_encode([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors
        ]), $statusCode);
    }

    /**
     * Rollback the database transaction and return an error response.
     *
     * @param string $message
     * @param array $errors
     * @param int $statusCode
     * @return JsonResponse
     */
    public static function rollback(string $message = 'Transaction Failed', array $errors = [], int $statusCode = 500): JsonResponse
    {
        DB::rollBack();

        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors
        ], $statusCode);
    }
}
