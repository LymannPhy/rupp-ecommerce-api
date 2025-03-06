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
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
        /**
     * Process the order.
     *
     * This method is responsible for:
     * - Validating order details.
     * - Checking payment status.
     * - Storing order, order items, order details, and payment if payment is successful.
     * - Clearing the user's cart after order completion.
     *
     * 🚀 **Request Body (JSON)**:
     * {
     *   "md5_hash": "123456789abcdef",
     *   "email": "user@example.com",
     *   "phone_number": "012345678",
     *   "province_uuid": "8e8a17df-1d8e-4f91-a24f-47bb3b128c11",
     *   "google_map_link": "https://maps.google.com/example",
     *   "remarks": "Leave at the door"
     * }
     *
     * @param Request $request The HTTP request containing order details.
     * @return JsonResponse The response confirming order success or failure.
     */
    public function confirmOrder(Request $request)
    {
        try {
            DB::beginTransaction();

            // 🔹 Validate required fields
            $validated = $request->validate([
                'md5_hash' => 'required|string',
                'email' => 'required|email',
                'phone_number' => 'required|string',
                'province_uuid' => 'required|exists:provinces,uuid',
                'google_map_link' => 'nullable|string',
                'remarks' => 'nullable|string',
            ]);

            $bakongToken = env('BAKONG_TOKEN');

            // 🔹 Send POST request to Bakong API to check payment
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$bakongToken}",
                'Content-Type' => 'application/json',
            ])->post('https://api-bakong.nbc.gov.kh/v1/check_transaction_by_md5', [
                'md5' => $validated['md5_hash'],
            ]);

            if ($response->failed()) {
                Log::error("Payment check failed with status: " . $response->status());
                return ApiResponse::error('QR Payment verification failed.', [], 400);
            }

            $paymentCheck = $response->json();
            Log::info("Payment found: ", $paymentCheck);

            if (!isset($paymentCheck['responseCode']) || $paymentCheck['responseCode'] !== 0) {
                return ApiResponse::error('Payment failed.', [], 400);
            }

            // ✅ Extract payment data
            $paymentData = $paymentCheck['data'];

            // 🔹 Fetch Province
            $province = \App\Models\Province::where('uuid', $validated['province_uuid'])->firstOrFail();

            // 🔹 Fetch Cart Items
            $cartItems = Cart::join('products', 'cart.product_id', '=', 'products.id')
                ->select(
                    'cart.product_id',
                    'products.uuid as product_uuid',
                    'products.name as product_name',
                    'cart.quantity',
                    'products.price'
                )
                ->where('cart.user_id', auth()->id())
                ->get();

            if ($cartItems->isEmpty()) {
                return ApiResponse::error('Your cart is empty.', [], 400);
            }

            // 🔹 Calculate Total Price (Products + Delivery Fee)
            $totalCartValue = $cartItems->sum(function ($item) {
                return $item->price * $item->quantity;
            });

            // 🔹 Store Order
            $order = Order::create([
                'user_id' => auth()->id(),
                'total_price' => $totalCartValue,
                'status' => 'processing',
            ]);

            // 🔹 Store Order Details
            OrderDetail::create([
                'order_id' => $order->id,
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'province_id' => $province->id,
                'google_map_link' => $validated['google_map_link'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            // 🔹 Store Order Items
            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                ]);
            }

            // 🔹 Store Payment Details
            Payment::create([
                'order_id' => $order->id,
                'user_id' => auth()->id(),
                'payment_method' => 'qr_code', 
                'amount' => $totalCartValue,
                'status' => 'paid', 
                'md5_hash' => $validated['md5_hash'],
                'transaction_hash' => $paymentData['externalRef'] ?? null,
                'from_account_id' => $paymentData['fromAccountId'] ?? 'Unknown',
                'to_account_id' => $paymentData['toAccountId'] ?? 'Unknown',
            ]);

            // 🔹 Clear User's Cart
            Cart::where('user_id', auth()->id())->delete();

            DB::commit();

            return ApiResponse::sendResponse([
                'order_id' => $order->uuid,
                'total_price' => $totalCartValue,
                'cart_items' => $cartItems,
            ], 'Order placed successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Checkout failed.', ['error' => $e->getMessage()], 500);
        }
    }

}
