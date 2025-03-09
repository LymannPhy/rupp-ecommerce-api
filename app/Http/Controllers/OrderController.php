<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Responses\ApiResponse;
use App\Models\Cart;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\OrderDetail;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Province;
use Illuminate\Support\Facades\DB;


class OrderController extends Controller
{
    public function submitOrder(Request $request)
    {
        try {
            DB::beginTransaction();

            $validated = $request->validate([
                'payment_id' => 'required|exists:payments,id', 
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

            // 🔹 Fetch province details
            $province = Province::where('uuid', $validated['province_uuid'])->firstOrFail();

            // 🔹 Determine estimated delivery date
            $deliveryDate = ($province->name === 'Phnom Penh') 
                ? now()->addDay(1)
                : now()->addDays(rand(2, 3));

            // 🔹 Get the last order ID and increment it
            $lastOrder = Order::latest('id')->first();
            $nextOrderNumber = $lastOrder ? $lastOrder->id + 1 : 1;
            $orderCode = 'ORD-' . str_pad($nextOrderNumber, 6, '0', STR_PAD_LEFT);

            // 🔹 Create Order
            $order = Order::create([
                'order_code' => $orderCode,
                'user_id' => auth()->id(),
                'total_price' => $validated['total_cart_value'],
                'delivery_price' => $validated['delivery_price'], 
                'delivery_method' => 'Delivery by Motor', 
                'delivery_date' => $deliveryDate, 
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

            // 🔹 Fetch cart items
            $cartItems = Cart::with('product')->where('user_id', auth()->id())->get();

            if ($cartItems->isEmpty()) {
                return response()->json([
                    'date' => now()->toDateTimeString(),
                    'status' => 'failed',
                    'message' => 'Your cart is empty.',
                ], 400);
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

            // 🔹 Link Payment to Order
            $payment = Payment::find($validated['payment_id']);
            $payment->update(['order_id' => $order->id]);

            // 🔹 Clear User Cart
            Cart::where('user_id', auth()->id())->delete();

            DB::commit();

            return response()->json([
                'date' => now()->toDateTimeString(),
                'status' => 'success',
                'message' => 'Order placed successfully! Payment linked.',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Checkout error: " . $e->getMessage());

            return response()->json([
                'date' => now()->toDateTimeString(),
                'status' => 'error',
                'message' => 'Checkout failed.',
            ], 500);
        }
    }


    /**
     * Get all orders of the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserOrders()
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return ApiResponse::error('Unauthorized ❌', ['error' => 'User not authenticated'], 401);
            }

            // 🔹 Fetch all user orders with related order items and product details
            $orders = Order::where('user_id', $user->id)
                ->with([
                    'orderItems.product' => function ($query) {
                        $query->select('id', 'uuid', 'name', 'price', 'multi_images', 'discount_id', 'is_preorder');
                    },
                    'orderItems.product.discount' => function ($query) {
                        $query->select('id', 'discount_percentage', 'is_active', 'start_date', 'end_date');
                    },
                    'coupon:id,code,discount_percentage',
                    'payment:id,order_id,amount' 
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            if ($orders->isEmpty()) {
                return ApiResponse::error('No orders found ❌', [], 404);
            }

            // 🔹 Format response data
            $formattedOrders = $orders->map(function ($order) {
                return [
                    'uuid' => $order->uuid,
                    'order_code' => $order->order_code,
                    'delivery_price' => $order->delivery_price,
                    'sub_total_price' => $order->total_price,
                    'total_price' => $order->payment ? $order->payment->amount : 0, 
                    'status' => $order->status,
                    'delivery_method' => $order->delivery_method,
                    'delivery_date' => $order->delivery_date,
                    'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                    'coupon' => $order->coupon ? [
                        'code' => $order->coupon->code,
                        'discount_percentage' => $order->coupon->discount_percentage,
                    ] : null,
                    'items' => $order->orderItems->map(function ($item) {
                        $product = $item->product;

                        // Calculate discounted price if applicable
                        $discountedPrice = $product->price;
                        if ($product->discount && $product->discount->is_active &&
                            now() >= $product->discount->start_date && now() <= $product->discount->end_date) {
                            $discountAmount = ($product->discount->discount_percentage / 100) * $product->price;
                            $discountedPrice = round($product->price - $discountAmount, 2);
                        }

                        return [
                            'product_uuid' => $product->uuid,
                            'product_name' => $product->name,
                            'quantity' => $item->quantity,
                            'original_price' => $product->price,
                            'discounted_price' => $discountedPrice, 
                            'total_price' => round($item->quantity * $discountedPrice, 2),
                            'is_preorder' => $product->is_preorder,
                            'image' => $product->multi_images ? json_decode($product->multi_images, true)[0] ?? null : null,
                        ];
                    }),
                ];
            });

            return ApiResponse::sendResponse($formattedOrders, 'User orders retrieved successfully ✅');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve orders 🔥', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Generate and download an invoice PDF for the given order using UUID.
     *
     * @param string $orderUuid The UUID of the order.
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse The generated PDF file.
     */
    public function generateInvoicePDF($orderUuid)
    {
        try {
            // 🔹 Fetch the Order with related details using UUID
            $order = Order::with([
                'details.province', // ✅ Fix: Use 'details' instead of 'orderDetail'
                'orderItems.product' => function ($query) {
                    $query->select('id', 'uuid', 'name', 'price', 'multi_images', 'discount_id', 'is_preorder');
                },
                'orderItems.product.discount' => function ($query) {
                    $query->select('id', 'discount_percentage', 'is_active', 'start_date', 'end_date');
                },
                'payment:id,order_id,amount'
            ])->where('uuid', $orderUuid)->firstOrFail();

            // 🔹 Ensure OrderDetail Exists
            if (!$order->details) {
                return ApiResponse::error('Order details not found.', [], 404);
            }

            // 🔹 Convert `delivery_date` to Carbon instance before formatting
            $deliveryDate = $order->delivery_date ? Carbon::parse($order->delivery_date)->format('F d, Y') : 'N/A';

            // 🔹 Prepare order data for rendering in Blade template
            $invoiceData = [
                'order' => [
                    'uuid' => $order->uuid,
                    'order_code' => $order->order_code,
                    'sub_total_price' => $order->total_price, 
                    'total_price' => $order->payment ? $order->payment->amount : 0,  
                    'delivery_price' => $order->delivery_price,
                    'status' => $order->status,
                    'delivery_method' => $order->delivery_method,
                    'delivery_date' => $deliveryDate, // ✅ Fixed conversion
                    'created_at' => $order->created_at->format('F d, Y'),
                    'coupon' => $order->coupon ? [
                        'code' => $order->coupon->code,
                        'discount_percentage' => $order->coupon->discount_percentage,
                    ] : null,
                    'items' => $order->orderItems->map(function ($item) {
                        $product = $item->product;

                        // Calculate discounted price if applicable
                        $discountedPrice = $product->price;
                        if ($product->discount && $product->discount->is_active &&
                            now() >= $product->discount->start_date && now() <= $product->discount->end_date) {
                            $discountAmount = ($product->discount->discount_percentage / 100) * $product->price;
                            $discountedPrice = round($product->price - $discountAmount, 2);
                        }

                        return [
                            'product_uuid' => $product->uuid,
                            'product_name' => $product->name,
                            'quantity' => $item->quantity,
                            'original_price' => $product->price,
                            'discounted_price' => $discountedPrice,
                            'total_price' => round($item->quantity * $discountedPrice, 2),
                            'image' => $product->multi_images ? json_decode($product->multi_images, true)[0] ?? null : null,
                            'is_preorder' => $product->is_preorder,
                        ];
                    }),
                ]
            ];

            // 🔹 Generate PDF from Blade view
            $pdf = Pdf::loadView('invoice', $invoiceData);

            // 🔹 Return the PDF as a Download
            return $pdf->download("invoice_{$order->uuid}.pdf");

        } catch (\Exception $e) {
            return ApiResponse::error('Something went wrong', ['details' => $e->getMessage()], 500);
        }
    }


    /**
     * Get the total amount of the order before proceeding to payment.
     */
    public function getOrderSummary(Request $request)
    {
        try {
            // 🔹 Validate request
            $validated = $request->validate([
                'province_uuid' => 'required|exists:provinces,uuid',
                'coupon_code' => 'nullable|string|exists:coupons,code',
            ]);

            // 🔹 Fetch Province and set delivery fee
            $province = \App\Models\Province::where('uuid', $validated['province_uuid'])->firstOrFail();
            $deliveryFee = ($province->name === 'Phnom Penh') ? 1.25 : 2.00;

            // 🔹 Fetch Cart Items
            $cartItems = Cart::join('products', 'cart.product_id', '=', 'products.id')
                ->leftJoin('discounts', 'products.discount_id', '=', 'discounts.id')
                ->select(
                    'cart.quantity',
                    'products.price',
                    'products.is_preorder',
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

            // ✅ Initialize totals
            $totalCartValue = 0;

            // 🔹 Process cart items and calculate total price
            foreach ($cartItems as $item) {
                $discountedPrice = $item->price;

                if ($item->discount_percentage > 0 && $item->is_active && now() >= $item->start_date && now() <= $item->end_date) {
                    $totalDiscount = ($item->discount_percentage / 100) * $item->price;
                    $discountedPrice = round($item->price - $totalDiscount, 2);
                }

                // 🔹 Preorder products only charge 50% of the price
                $totalProductPrice = $item->is_preorder
                    ? round(($discountedPrice * $item->quantity) / 2, 2)
                    : round($discountedPrice * $item->quantity, 2);

                $totalCartValue += $totalProductPrice;
            }

            // ✅ Initialize Coupon Discount
            $couponDiscount = 0.00;

            // 🔹 Check if a coupon is provided and apply discount
            if (!empty($validated['coupon_code'])) {
                $coupon = \App\Models\Coupon::where('code', $validated['coupon_code'])->first();

                if (!$coupon) {
                    return ApiResponse::error('Invalid or expired coupon.', [], 400);
                }

                if (!$coupon->isValid()) {
                    return ApiResponse::error('Coupon is expired or inactive.', [], 400);
                }

                if ($coupon->hasReachedMaxUsage()) {
                    return ApiResponse::error('This coupon has reached its maximum usage limit.', [], 400);
                }

                if ($coupon->hasUserExceededLimit(auth()->id())) {
                    return ApiResponse::error('You have already used this coupon the maximum allowed times.', [], 400);
                }

                // ✅ Apply discount based on type
                if (strtolower($coupon->discount_type) === 'percentage') {
                    $couponDiscount = round(($coupon->discount_percentage / 100) * $totalCartValue, 2);
                } elseif (strtolower($coupon->discount_type) === 'fixed') {
                    $couponDiscount = min(round($coupon->discount_value, 2), $totalCartValue);
                }
            }

            // ✅ Calculate Final Total (Cart Total - Coupon Discount + Delivery Fee)
            $finalTotal = max(round(($totalCartValue - $couponDiscount) + $deliveryFee, 2), 0);

            // ✅ Return Only Required Fields
            return ApiResponse::sendResponse([
                'total_cart_value' => $totalCartValue,
                'coupon_discount' => $couponDiscount,
                'delivery_fee' => $deliveryFee,
                'final_total' => $finalTotal,
            ], 'Total amount calculated successfully.');

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to calculate total amount.', ['error' => $e->getMessage()], 500);
        }
    }



}
