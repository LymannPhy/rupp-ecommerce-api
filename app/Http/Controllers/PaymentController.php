<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\KhqrService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller {
    private KhqrService $khqrService;

    public function __construct(KhqrService $khqrService) {
        $this->khqrService = $khqrService;
    }

    public function checkout(Request $request): JsonResponse {
        try {
            // Set the user statically (for example, user with ID = 1)
            $user = User::find(1); // Replace '1' with your static user ID
    
            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }
    
            // Fetch the user's cart items (assuming cart is already populated)
            $cartItems = Cart::where('user_id', $user->id)->get();
    
            if ($cartItems->isEmpty()) {
                return response()->json(['error' => 'No items in cart'], 400);
            }
    
            // Generate a unique UUID for the new order
            $uuid = (string) \Illuminate\Support\Str::uuid();
    
            // Create an order with a unique UUID
            $order = Order::create([
                'uuid' => $uuid, // Ensure UUID is unique
                'user_id' => $user->id,
                'total_price' => $cartItems->sum('price'), // Calculate total price based on cart items
            ]);
    
            // Create payment for the order and associate with the user
            $payment = Payment::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(), // Generate a unique payment UUID
                'order_id' => $order->id,
                'amount' => $order->total_price,
                'user_id' => $user->id,  // Associate payment with the user
            ]);
    
            // Generate the QR code for payment
            $qrData = $this->khqrService->generateQrCode($payment);
    
            // Return the QR code data
            return response()->json($qrData);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    

    public function checkPayment($paymentId): JsonResponse {
        $payment = Payment::findOrFail($paymentId);

        if ($this->khqrService->checkPaymentStatus($payment)) {
            return response()->json(['status' => 'completed']);
        }

        return response()->json(['status' => 'pending']);
    }

    public function webhook(Request $request): JsonResponse {
        $md5Hash = $request->input('md5');

        $payment = Payment::where('md5_hash', $md5Hash)->first();

        if (!$payment) {
            return response()->json(['error' => 'Payment not found'], 404);
        }

        $payment->update(['status' => 'completed']);

        return response()->json(['message' => 'Payment updated']);
    }
}

