<?php

namespace App\Http\Controllers;

use App\Helpers\PaginationHelper;
use Illuminate\Http\Request;
use App\Http\Responses\ApiResponse;
use App\Models\Cart;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Models\OrderDetail;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Province;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Exceptions\HttpResponseException;


class OrderController extends Controller
{
    public function getOrdersByDateRange(Request $request)
    {
        $perPage = $request->query('per_page', 10); 
        $startDate = $request->query('start_date'); 
        $endDate = $request->query('end_date');   
        $sortParam = $request->query('sort', 'desc'); 
    
        $ordersQuery = Order::query();
    
        // Optional Filter by Start & End Date
        if ($startDate && $endDate) {
            $ordersQuery->whereBetween('created_at', [$startDate, $endDate]);
        } elseif ($startDate) {
            $ordersQuery->whereDate('created_at', '>=', $startDate);
        } elseif ($endDate) {
            $ordersQuery->whereDate('created_at', '<=', $endDate);
        }
    
        // Optional Sort by total_price
        if (in_array(strtolower($sortParam), ['asc', 'desc'])) {
            $ordersQuery->orderBy('total_price', $sortParam);
        } else {
            $ordersQuery->orderBy('created_at', 'desc'); // default sort
        }
    
        // Paginate the orders
        $paginatedOrders = $ordersQuery->paginate($perPage);
    
        // Format data
        $orders = $paginatedOrders->getCollection()->map(function ($order) {
            return [
                'order_code' => $order->order_code,
                'total_price' => $order->total_price,
                'status' => $order->status,
                'delivery_date' => $order->delivery_date,
                'created_at' => $order->created_at,
            ];
        });
    
        return ApiResponse::sendResponse(
            PaginationHelper::formatPagination($paginatedOrders, $orders),
            'Orders retrieved successfully'
        );
    }
    




    /**
     * Get a specific order of the authenticated user by UUID.
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserOrderByUuid($uuid)
    {
        
        try {
            $user = auth()->user();

            if (!$user) {
                return ApiResponse::error('Unauthorized ❌', ['error' => 'User not authenticated'], 401);
            }

            // 🔹 Fetch the specific order with related data
            $order = Order::where('user_id', $user->id)
                ->where('uuid', $uuid)
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
                ->first();

            if (!$order) {
                return ApiResponse::error('Order not found ❌', [], 404);
            }

            // 🔹 Format the response data
            $formattedOrder = [
                'uuid' => $order->uuid,
                'order_code' => $order->order_code,
                'delivery_fee' => $order->delivery_fee,
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
                        // Check if multi_images is a string or already an array
                        'image' => $product->multi_images 
                        ? (is_string($product->multi_images) ? json_decode($product->multi_images, true) : $product->multi_images)[0] ?? null 
                        : null,
                    ];
                }),
            ];

            return ApiResponse::sendResponse($formattedOrder, 'User order retrieved successfully ✅');
            
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve order 🔥', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Get user payment invoice data as JSON.
     *
     * @param string $orderUuid The UUID of the order.
     * @return JsonResponse
     */
    public function getUserPaymentInvoiceData($orderUuid)
    {
        try {
            // Fetch the order with related payment information
            $order = Order::with(['payment'])
                ->where('uuid', $orderUuid)
                ->first();

            // Handle case where order is not found
            if (!$order) {
                return ApiResponse::error('Order not found.', [], 404);
            }

            // Ensure payment exists for the order
            if (!$order->payment) {
                return ApiResponse::error('Payment information not found for this order.', [], 404);
            }

            // Prepare invoice data
            $invoiceData = [
                'order_code' => $order->order_code,
                'from_account' => $order->payment->from_account_id,
                'to_account' => $order->payment->to_account_id,
                'amount' => $order->payment->amount,
                'payment_date' => Carbon::parse($order->payment->created_at)->format('F d, Y'),
                'transaction_place' => $order->payment->transaction_place,
            ];

            // Send successful response using ApiResponse class
            return ApiResponse::sendResponse($invoiceData, 'Invoice data retrieved successfully.');

        } catch (\Exception $e) {
            // Handle and return any unexpected error
            return ApiResponse::error('Something went wrong.', ['error' => $e->getMessage()], 500);
        }
    }


    public function submitOrder(Request $request)
    {
        try {
            DB::beginTransaction();

            $validated = $request->validate([
                'payment_id' => 'required|exists:payments,id', 
                'final_total' => 'required|numeric|min:0', 
                'province_uuid' => 'required|exists:provinces,uuid',
                'delivery_fee' => 'required|numeric|min:0', 
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
                'total_price' => $validated['final_total'],
                'delivery_fee' => $validated['delivery_fee'], 
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

            // 🔹 Store Order Items & Deduct Stock
            foreach ($cartItems as $item) {
                // Create order item
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product->id,
                    'quantity' => $item->quantity,
                    'price' => $item->product->price,
                    'discounted_price' => $item->product->discounted_price ?? null,
                ]);

                // Decrease product stock
                $product = $item->product;
                if ($product->stock < $item->quantity) {
                    throw new \Exception("Insufficient stock for product: {$product->name}");
                }

                $product->decrement('stock', $item->quantity);
            }


            // 🔹 Link Payment to Order
            $payment = Payment::find($validated['payment_id']);
            $payment->update(['order_id' => $order->id]);

            // 🔹 Clear User Cart
            Cart::where('user_id', auth()->id())->delete();

            // ✅ Send Telegram Alert
            $this->sendTelegramAlert($order, $validated);

            DB::commit();

            return response()->json([
                'date' => now()->toDateTimeString(),
                'status' => 'success',
                'message' => 'Order placed successfully! Payment linked.',
                'order_uuid' => $order->uuid, 
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
        
            return response()->json([
                'date' => now()->toDateTimeString(),
                'status' => 'error',
                'message' => 'Checkout failed.',
                'error' => [
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
        
    }

    /**
     * Send order alert to Telegram Bot
     *
     * @param $order
     * @param $validated
     * @throws HttpResponseException
     */
    private function sendTelegramAlert($order, $validated)
    {
        $telegramToken = env('TELEGRAM_BOT_TOKEN'); 
        $chatId = env('TELEGRAM_CHAT_ID');         

        $message = "📦 *New Order Alert!*\n"
            . "*Order Code:* {$order->order_code}\n"
            . "*User Email:* {$validated['email']}\n"
            . "*Phone Number:* {$validated['phone_number']}\n"
            . "*Total Price:* {$validated['final_total']} USD\n"
            . "*Delivery Date:* {$order->delivery_date}\n"
            . "*Address:* {$validated['current_address']}\n"
            . "*Remarks:* " . (isset($validated['remarks']) ? $validated['remarks'] : 'N/A');

        $url = "https://api.telegram.org/bot{$telegramToken}/sendMessage";

        // Send the message to Telegram
        $response = Http::withOptions(['verify' => false])->post($url, [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown'
        ]);

        // Throw an exception if the Telegram alert fails
        if ($response->failed()) {
            $errorDetails = $response->json();

            throw new HttpResponseException(response()->json([
                'status' => 'error',
                'message' => 'Failed to send order alert to Telegram.',
                'telegram_error' => $errorDetails
            ], 500));
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
                    'delivery_date' => $order->delivery_date ? $order->delivery_date->format('Y-m-d') : null,  // Now it will work without error
                    'created_at' => $order->created_at ? $order->created_at->format('Y-m-d H:i:s') : null,
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
                            'image' => $product->multi_images 
                            ? (is_string($product->multi_images) ? json_decode($product->multi_images, true) : $product->multi_images)[0] ?? null 
                            : null,
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
                'details.province',
                'orderItems.product' => function ($query) {
                    $query->select('id', 'uuid', 'name', 'price', 'multi_images', 'discount_id', 'is_preorder');
                },
                'orderItems.product.discount' => function ($query) {
                    $query->select('id', 'discount_percentage', 'is_active', 'start_date', 'end_date');
                },
                'coupon:id,code,discount_percentage,discount_value,discount_type',
                'payment:id,order_id,amount'
            ])->where('uuid', $orderUuid)->firstOrFail();

            // 🔹 Ensure Order Details Exist
            if (!$order->details) {
                return ApiResponse::error('Order details not found.', [], 404);
            }

            // 🔹 Convert `delivery_date` to Carbon instance before formatting
            $deliveryDate = $order->delivery_date ? Carbon::parse($order->delivery_date)->format('F d, Y') : 'N/A';

            // ✅ Correct Calculation of Order Totals
            $totalProductCost = 0;
            $orderItems = $order->orderItems->map(function ($item) use (&$totalProductCost) {
                $product = $item->product;

                // Apply discount if available
                $discountedPrice = $product->price;
                if ($product->discount && $product->discount->is_active &&
                    now() >= $product->discount->start_date && now() <= $product->discount->end_date) {
                    $discountAmount = ($product->discount->discount_percentage / 100) * $product->price;
                    $discountedPrice = round($product->price - $discountAmount, 2);
                }

                // Apply Preorder Rule (50% charge)
                $totalItemPrice = $product->is_preorder
                    ? round(($discountedPrice * $item->quantity) / 2, 2)
                    : round($discountedPrice * $item->quantity, 2);

                $totalProductCost += $totalItemPrice;

                return [
                    'product_uuid' => $product->uuid,
                    'product_name' => $product->name,
                    'quantity' => $item->quantity,
                    'original_price' => $product->price,
                    'discounted_price' => $discountedPrice,
                    'total_price' => $totalItemPrice,
                    'image' => $product->multi_images ? json_decode($product->multi_images, true)[0] ?? null : null,
                    'is_preorder' => $product->is_preorder,
                ];
            });

            // ✅ Apply Coupon Discount
            $couponDiscount = 0;
            if ($order->coupon) {
                if (strtolower($order->coupon->discount_type) === 'percentage') {
                    $couponDiscount = round(($order->coupon->discount_percentage / 100) * $totalProductCost, 2);
                } elseif (strtolower($order->coupon->discount_type) === 'fixed') {
                    $couponDiscount = min(round($order->coupon->discount_value, 2), $totalProductCost);
                }
            }

            // ✅ Final Total Calculation
            $finalTotal = max(round(($totalProductCost - $couponDiscount) + $order->delivery_price, 2), 0);

            // 🔹 Prepare invoice data
            $invoiceData = [
                'order' => [
                    'uuid' => $order->uuid,
                    'order_code' => $order->order_code,
                    'sub_total_price' => $totalProductCost,
                    'total_price' => $finalTotal,
                    'delivery_price' => $order->delivery_price,
                    'status' => $order->status,
                    'delivery_method' => $order->delivery_method,
                    'delivery_date' => $deliveryDate,
                    'created_at' => $order->created_at->format('F d, Y'),
                    'coupon' => $order->coupon ? [
                        'code' => $order->coupon->code,
                        'discount_percentage' => $order->coupon->discount_percentage,
                        'discount_value' => $order->coupon->discount_value,
                        'discount_type' => $order->coupon->discount_type,
                        'applied_discount' => $couponDiscount,
                    ] : null,
                    'orderDetail' => $order->details ? [
                        'address' => $order->details->address ?? 'N/A',
                        'province' => $order->details->province ? [
                            'name' => $order->details->province->name
                        ] : ['name' => 'N/A'],
                    ] : ['address' => 'N/A', 'province' => ['name' => 'N/A']],
                    'items' => $orderItems,
                ]
            ];
            

            // 🔹 Generate PDF from Blade view
            $pdf = Pdf::loadView('invoice_details', $invoiceData);

            // 🔹 Return the PDF as a Download
            return $pdf->download("invoice_details_{$order->uuid}.pdf");

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

            // ✅ Ensure the discount percentage is never NULL
            $couponDiscount = 0.00;

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
            
                // ✅ Ensure null values are treated as 0
                $coupon->discount_percentage = $coupon->discount_percentage ?? 0;
                $coupon->discount_value = $coupon->discount_value ?? 0;
            
                // ✅ Apply discount based on type
                if (strtolower($coupon->discount_type) === 'percentage') {
                    $couponDiscount = round(($coupon->discount_percentage / 100) * max($totalCartValue, 0), 2);
                } elseif (strtolower($coupon->discount_type) === 'fixed') {
                    $couponDiscount = min(round($coupon->discount_value, 2), $totalCartValue);
                }
            
                // ✅ Ensure that a coupon applied with NULL discount doesn't return an error
                if ($couponDiscount <= 0 && $totalCartValue > 0) {
                    return ApiResponse::error('Coupon applied but does not provide any discount.', [], 400);
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
