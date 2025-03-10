<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Payment;

class PaymentController extends Controller
{
    public function checkMd5Hash(Request $request)
    {
        $validated = $request->validate([
            'md5_hash' => 'required|string',
        ]);

        $bakongToken = env('BAKONG_TOKEN');
        $maxRetries = 5;
        $retryDelay = 5;
        $attempt = 0;
        $paymentCheck = null;

        do {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$bakongToken}",
                'Content-Type' => 'application/json',
            ])->post('https://api-bakong.nbc.gov.kh/v1/check_transaction_by_md5', [
                'md5' => $validated['md5_hash'],
            ]);

            if ($response->successful()) {
                $paymentCheck = $response->json();
            }

            if (isset($paymentCheck['responseCode']) && $paymentCheck['responseCode'] === 0) {
                break;
            }

            $attempt++;
            if ($attempt < $maxRetries) {
                sleep($retryDelay);
            }
        } while ($attempt < $maxRetries);

        if (!$paymentCheck || !isset($paymentCheck['responseCode']) || $paymentCheck['responseCode'] !== 0) {
            return response()->json([
                'date' => now()->toDateTimeString(),
                'status' => 'failed',
                'message' => $paymentCheck['message'] ?? 'Payment verification failed after multiple attempts.',
            ], 400);
        }

        // 🔹 Validate payment data
        $paymentData = $paymentCheck['data'] ?? null;
        if (!$paymentData) {
            return response()->json([
                'date' => now()->toDateTimeString(),
                'status' => 'failed',
                'message' => 'Invalid payment data received.',
            ], 400);
        }

        // 🔹 Store Payment Details immediately after verification
        $payment = Payment::create([
            'order_id' => null, 
            'user_id' => auth()->id(),
            'payment_method' => 'qr_code',
            'amount' => $paymentData['amount'] ?? 0, 
            'status' => 'paid', 
            'md5_hash' => $validated['md5_hash'],
            'transaction_hash' => $paymentData['externalRef'] ?? 'N/A',
            'from_account_id' => $paymentData['fromAccountId'] ?? 'Unknown',
            'to_account_id' => $paymentData['toAccountId'] ?? 'Unknown',
            'transaction_place' => 'Asia/Phnom Penh',
        ]);

        return response()->json([
            'date' => now()->toDateTimeString(),
            'status' => 'success',
            'message' => 'Payment verification successful. Payment stored.',
            'payment_id' => $payment->id,
        ], 200);
    }
}
