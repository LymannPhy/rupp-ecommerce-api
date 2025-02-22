<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Http\Responses\ApiResponse;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Log;


class OrderController extends Controller
{
    protected $paymentController; 

    // ✅ Inject PaymentController into the constructor
    public function __construct(PaymentController $paymentController)
    {
        $this->paymentController = $paymentController;
    }

    public function checkout(Request $request)
    {
        try {
            DB::beginTransaction();

            // 🔹 Validate required fields
            $validated = $request->validate([
                'email' => 'required|email',
                'phone_number' => 'required|string',
                'current_address' => 'required|string',
                'google_map_link' => 'nullable|string',
                'remarks' => 'nullable|string',
                'payment_method' => 'required|string|in:credit_card,paypal,cash_on_delivery,qr_code',
                'md5_hash' => 'required_if:payment_method,qr_code|string',
            ]);

            // 🔹 Fetch Cart Items
            $cartItems = Cart::join('products', 'cart.product_id', '=', 'products.id')
                ->leftJoin('discounts', 'products.discount_id', '=', 'discounts.id')
                ->select(
                    'cart.product_id',
                    'products.uuid as product_uuid',
                    'products.name as product_name',
                    'cart.quantity',
                    'products.price',
                    'discounts.discount_percentage',
                    'discounts.is_active',
                    'discounts.start_date',
                    'discounts.end_date'
                )
                ->where('cart.user_id', auth()->id())
                ->get();

            if ($cartItems->isEmpty()) {
                return ApiResponse::error('Your cart is empty.', [], 400);
            }

            $totalCartValue = 0;

            // 🔹 Calculate total price
            $processedCartItems = $cartItems->map(function ($item) use (&$totalCartValue) {
                $discountedPrice = $item->price;
                $totalDiscount = 0;

                if ($item->discount_percentage > 0 && $item->is_active && now() >= $item->start_date && now() <= $item->end_date) {
                    $totalDiscount = ($item->discount_percentage / 100) * $item->price;
                    $discountedPrice = round($item->price - $totalDiscount, 2);
                }

                $totalProductPrice = round($discountedPrice * $item->quantity, 2);
                $totalCartValue += $totalProductPrice;

                return [
                    'product_uuid' => $item->product_uuid,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'original_price' => $item->price,
                    'discount_percentage' => $item->discount_percentage ?? 0,
                    'total_discount' => round($totalDiscount * $item->quantity, 2),
                    'discounted_price' => $discountedPrice,
                    'total_price' => $totalProductPrice,
                ];
            });

            // 🔹 Create Order
            $order = Order::create([
                'user_id' => auth()->id(),
                'total_price' => $totalCartValue,
                'status' => 'pending',
            ]);

            // 🔹 Save Order Details
            OrderDetail::create([
                'order_id' => $order->id,
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'current_address' => $validated['current_address'],
                'google_map_link' => $validated['google_map_link'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            // 🔹 Payment Handling (QR Code Transaction Verification)
            if ($validated['payment_method'] === 'qr_code') {
            
                $paymentCheckResponse = $this->paymentController->checkPayment(new Request(['md5' => $validated['md5_hash']]));
            
                $paymentCheck = json_decode(json_encode($paymentCheckResponse->getData()), true); // Convert to array

                // ✅ Log the full API response from checkPayment()
                Log::info("📨 Payment API Response:", $paymentCheck);
            
                // ✅ Check if API response is successful based on `code` field
                if (!isset($paymentCheck['code']) || $paymentCheck['code'] !== 200 || !isset($paymentCheck['data'])) {
                    return ApiResponse::error('QR Payment verification failed.', ['response' => $paymentCheck], 400);
                }
            
                // ✅ Extract payment data
                $paymentData = $paymentCheck['data'];
            
                Payment::create([
                    'order_id' => $order->id,
                    'transaction_hash' => $validated['md5_hash'],
                    'from_account_id' => $paymentData['fromAccountId'] ?? 'Unknown',
                    'to_account_id' => $paymentData['toAccountId'] ?? 'Unknown',
                    'currency' => $paymentData['currency'] ?? 'USD',
                    'amount' => $paymentData['amount'] ?? 0,
                    'description' => $paymentData['description'] ?? null,
                    'created_date' => Carbon::createFromTimestampMs($paymentData['createdDateMs']),
                    'acknowledged_date' => isset($paymentData['acknowledgedDateMs']) 
                        ? Carbon::createFromTimestampMs($paymentData['acknowledgedDateMs']) 
                        : null,
                    'external_ref' => $paymentData['externalRef'] ?? null,
                    'payment_status' => 'paid',
                ]);
            
                // ✅ Update order status to processing
                $order->update(['status' => 'processing']);
            }
            

            // 🔹 Clear User's Cart
            Cart::where('user_id', auth()->id())->delete();

            DB::commit();

            return ApiResponse::sendResponse([
                'order_id' => $order->uuid,
                'total_price' => $totalCartValue,
                'cart_items' => $processedCartItems,
            ], 'Order placed successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Checkout failed.', ['error' => $e->getMessage()], 500);
        }
    }
}
