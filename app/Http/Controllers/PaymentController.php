<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Http\Responses\ApiResponse;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Province;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function checkPayment(Request $request)
    {
        try {
            DB::beginTransaction();

            // 🔹 Validate request
            $validated = $request->validate([
                'md5_hash' => 'required|string',
                'total_cart_value' => 'required|numeric|min:0',
                'final_total' => 'required|numeric|min:0', 
                'province_uuid' => 'required|exists:provinces,uuid',
                'delivery_price' => 'required|numeric|min:0', 
                'email' => 'required|email',
                'phone_number' => 'required|string',
                'current_address' => 'required|string',
                'google_map_link' => 'nullable|url',
                'remarks' => 'nullable|string',
            ]);

            $bakongToken = env('BAKONG_TOKEN');

            // 🔹 Call Bakong API to verify payment
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$bakongToken}",
                'Content-Type' => 'application/json',
            ])->post('https://api-bakong.nbc.gov.kh/v1/check_transaction_by_md5', [
                'md5' => $validated['md5_hash'],
            ]);

            if ($response->failed()) {
                Log::error("Payment check failed: " . $response->body());
                return ApiResponse::error('QR Payment verification failed.', [], 400);
            }

            $paymentCheck = $response->json();
            Log::info("Payment API Response: ", $paymentCheck);

            // 🔹 Validate response structure
            if (!isset($paymentCheck['responseCode']) || $paymentCheck['responseCode'] !== 0) {
                return response()->json([
                    'date' => now()->toDateTimeString(),
                    'code' => 400,
                    'message' => $paymentCheck['message'] ?? 'Payment failed.',
                    'errors' => $paymentCheck['errors'] ?? [],
                ], 400);
            }

            // 🔹 Validate payment data
            $paymentData = $paymentCheck['data'] ?? null;
            if (!$paymentData) {
                return response()->json([
                    'date' => now()->toDateTimeString(),
                    'code' => 400,
                    'message' => 'Invalid payment data received.',
                    'errors' => [],
                ], 400);
            }

            // 🔹 Fetch province details
            $province = Province::where('uuid', $validated['province_uuid'])->firstOrFail();

            // 🔹 Determine estimated delivery date based on province
            if ($province->name === 'Phnom Penh') {
                $deliveryDate = now()->addDay(1); // 1-day delivery for Phnom Penh
            } else {
                $deliveryDate = now()->addDays(rand(2, 3)); // 2 to 3 days for other provinces
            }

            // 🔹 Create Order with delivery details
            $order = Order::create([
                'user_id' => auth()->id(),
                'total_price' => $validated['total_cart_value'],
                'delivery_price' => $validated['delivery_price'], // Now taken from request body
                'delivery_method' => 'Delivery by Motor', // Static value
                'delivery_date' => $deliveryDate, // Dynamically calculated
                'status' => 'processing',
            ]);

            // 🔹 Store Order Details in `order_details` table
            OrderDetail::create([
                'order_id' => $order->id,
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'province_id' => $province->id,
                'google_map_link' => $validated['google_map_link'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            // 🔹 Fetch cart items
            $cartItems = Cart::with('product')
                ->where('cart.user_id', auth()->id())
                ->get();

            if ($cartItems->isEmpty()) {
                return ApiResponse::error('Your cart is empty.', [], 400);
            }

            // 🔹 Store Order Items
            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product->id,
                    'quantity' => $item->quantity,
                    'price' => $item->product->price,
                ]);
            }

            // 🔹 Store Payment Details with final total
            Payment::create([
                'order_id' => $order->id,
                'user_id' => auth()->id(),
                'payment_method' => 'qr_code',
                'amount' => $validated['final_total'], // Store final total in payments
                'status' => 'paid',
                'md5_hash' => $validated['md5_hash'],
                'transaction_hash' => $paymentData['externalRef'] ?? 'N/A',
                'from_account_id' => $paymentData['fromAccountId'] ?? 'Unknown',
                'to_account_id' => $paymentData['toAccountId'] ?? 'Unknown',
                'transaction_place' => 'Asia/Phnom Penh',
            ]);

            // 🔹 Clear User Cart
            Cart::where('user_id', auth()->id())->delete();

            DB::commit();

            return response()->json([
                'date' => now()->toDateTimeString(),
                'code' => 200,
                'message' => 'Order placed successfully!',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Checkout error: " . $e->getMessage());

            return response()->json([
                'date' => now()->toDateTimeString(),
                'code' => 500,
                'message' => 'Checkout failed.',
                'errors' => ['error' => $e->getMessage()],
            ], 500);
        }
    }

}
