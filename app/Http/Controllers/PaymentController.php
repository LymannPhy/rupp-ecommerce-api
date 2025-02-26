<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Http\Responses\ApiResponse;

class PaymentController extends Controller
{
    public function checkPayment(Request $request)
    {
        // Retrieve the MD5 hash from the request body
        $md5Hash = $request->input('md5');

        Log::info("Checking payment {$md5Hash}");

        $bakongToken = env('BAKONG_TOKEN');

        try {
            // 🔹 Send POST request to Bakong API
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$bakongToken}",
                'Content-Type' => 'application/json',
            ])->post('https://api-bakong.nbc.gov.kh/v1/check_transaction_by_md5', [
                'md5' => $md5Hash,
            ]);

            if ($response->failed()) {
                Log::error("Payment check failed with status: " . $response->status());
                return ApiResponse::error('Payment check failed.', [], 400);
            }

            $responseData = $response->json();
            Log::info("Payment found: ", $responseData);

            // 🔹 Check if payment is successful
            if (isset($responseData['responseCode']) && $responseData['responseCode'] === 0) {
                Log::info("Payment successful");

                $data = $responseData['data'];

                return ApiResponse::sendResponse([
                    'fromAccountId' => $data['fromAccountId'] ?? 'N/A',
                    'toAccountId' => $data['toAccountId'] ?? 'N/A',
                    'currency' => $data['currency'] ?? 'N/A',
                    'amount' => $data['amount'] ?? 0,
                    'description' => $data['description'] ?? null,
                    'createdDateMs' => $data['createdDateMs'] ?? null,
                    'acknowledgedDateMs' => $data['acknowledgedDateMs'] ?? null,
                    'externalRef' => $data['externalRef'] ?? 'N/A',
                ], 'Payment successful');
            } else {
                Log::error("Payment check failed with response code: " . ($responseData['responseCode'] ?? 'Unknown'));
                return ApiResponse::error('Payment failed.', [], 400);
            }

        } catch (\Exception $e) {
            Log::error("Error checking payment: " . $e->getMessage());
            return ApiResponse::error('Error checking payment.', [], 500);
        }
    }
}
