<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Responses\ApiResponse;
use App\Models\Cart;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use App\Models\Coupon;
use Barryvdh\DomPDF\Facade\Pdf;


class OrderController extends Controller
{
    /**
     * Generate an invoice PDF for the given order using UUID.
     *
     * @param string $orderUuid The UUID of the order.
     * @return \Illuminate\Http\Response The generated PDF file.
     */
    public function generateInvoicePDF($orderUuid)
    {
        try {
            // 🔹 Fetch the Order with related details using UUID instead of ID
            $order = Order::with(['orderDetail.province', 'orderItems.product'])
                ->where('uuid', $orderUuid)
                ->firstOrFail();

            // 🔹 Load the Invoice Blade View & Pass Data
            $pdf = Pdf::loadView('invoice', compact('order'));

            // 🔹 Return the PDF as a Download
            return $pdf->download("invoice_{$order->uuid}.pdf");

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to generate invoice.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get the total amount of the order before proceeding to payment.
     * 
     * This method calculates:
     * - The total price of all cart items (after applying discounts).
     * - The delivery fee based on the selected province.
     * - If a product is a preorder, only 50% of its price is charged now.
     * 
     * 🚀 **Request Body (JSON)**:
     * {
     *   "province_uuid": "8e8a17df-1d8e-4f91-a24f-47bb3b128c11",
     *   "coupon_code": "DISCOUNT2024"
     * }
     * 
     * @param Request $request (Requires `province_uuid`, optional `coupon_code`)
     * @return JsonResponse (Returns `cart_items`, `preorder_total`, `regular_total`, `total_price`)
     */
    public function getOrderSummary(Request $request)
    {
        try {
            // 🔹 Validate request
            $validated = $request->validate([
                'province_uuid' => 'required|exists:provinces,uuid',
                'coupon_code' => 'nullable|string|exists:coupons,code',
            ]);

            // 🔹 Fetch Province
            $province = \App\Models\Province::where('uuid', $validated['province_uuid'])->firstOrFail();
            $deliveryFee = ($province->name === 'Phnom Penh') ? 1.25 : 2.00;

            // 🔹 Fetch Cart Items
            $cartItems = Cart::join('products', 'cart.product_id', '=', 'products.id')
                ->leftJoin('discounts', 'products.discount_id', '=', 'discounts.id')
                ->select(
                    'cart.product_id',
                    'products.uuid as product_uuid',
                    'products.name as product_name',
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
            $preorderTotal = 0;
            $regularTotal = 0;
            $totalCartValue = 0;

            // 🔹 Process cart items and calculate totals
            $processedCartItems = $cartItems->map(function ($item) use (&$preorderTotal, &$regularTotal, &$totalCartValue) {
                $discountedPrice = $item->price;
                $totalDiscount = 0;

                if ($item->discount_percentage > 0 && $item->is_active && now() >= $item->start_date && now() <= $item->end_date) {
                    $totalDiscount = ($item->discount_percentage / 100) * $item->price;
                    $discountedPrice = round($item->price - $totalDiscount, 2);
                }

                if ($item->is_preorder) {
                    $totalProductPrice = round(($discountedPrice * $item->quantity) / 2, 2);
                    $preorderTotal += $totalProductPrice;
                } else {
                    $totalProductPrice = round($discountedPrice * $item->quantity, 2);
                    $regularTotal += $totalProductPrice;
                }

                $totalCartValue += $totalProductPrice;

                return [
                    'product_uuid' => $item->product_uuid,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'original_price' => $item->price,
                    'discounted_price' => $discountedPrice,
                    'total_price' => $totalProductPrice,
                    'is_preorder' => $item->is_preorder,
                ];
            });

            // 🔹 Apply Coupon Discount **Only If Applicable**
            $couponDiscount = 0;
            if (!empty($request->coupon_code)) {
                $coupon = Coupon::where('code', $request->coupon_code)->first();

                if ($coupon && $coupon->isValid()) {
                    $couponDiscount = round(($coupon->discount_percentage / 100) * $totalCartValue, 2);

                    // ✅ Ensure Preorder Discount Never Goes Negative
                    if ($preorderTotal > 0) {
                        $preorderTotal = max(0, round($preorderTotal - ($couponDiscount / 2), 2));
                    }
                    $regularTotal = max(0, round($regularTotal - ($couponDiscount / 2), 2));
                }
            }

            // ✅ Calculate Final Price
            $totalPrice = round($preorderTotal + $regularTotal + $deliveryFee, 2);

            // ✅ Return Only Cart Items & Total Price
            return ApiResponse::sendResponse([
                'cart_items' => $processedCartItems,
                'preorder_total' => $preorderTotal, // ✅ 50% charge of preorder items
                'regular_total' => $regularTotal, // ✅ Full charge of regular items
                'coupon_discount' => $couponDiscount,
                'delivery_fee' => $deliveryFee,
                'total_price' => $totalPrice, // ✅ Final amount after coupon & delivery fee
            ], 'Total amount calculated successfully.');

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to calculate total amount.', ['error' => $e->getMessage()], 500);
        }
    }


        /**
     * Process the order.
     *
     * This method is responsible for:
     * - Validating order details.
     * - Clearing the user's cart after order completion.
     *
     * 🚀 **Request Body (JSON)**:
     * {
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
                'email' => 'required|email',
                'phone_number' => 'required|string',
                'province_uuid' => 'required|exists:provinces,uuid',
                'google_map_link' => 'nullable|string',
                'remarks' => 'nullable|string',
            ]);

            // 🔹 Fetch Province by UUID
            $province = \App\Models\Province::where('uuid', $validated['province_uuid'])->firstOrFail();

            // ✅ Set Delivery Fee Based on Province Name
            $deliveryFee = ($province->name === 'Phnom Penh') ? 1.25 : 2.00;

            // 🔹 Fetch Cart Items
            $cartItems = Cart::join('products', 'cart.product_id', '=', 'products.id')
                ->select(
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

            $totalWithDelivery = round($totalCartValue + $deliveryFee, 2);

            return ApiResponse::sendResponse([
                'total_price' => $totalWithDelivery,
                'cart_items' => $cartItems,
                'delivery_fee' => $deliveryFee,
                'province' => $province->name,
            ], 'Order processed successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Checkout failed.', ['error' => $e->getMessage()], 500);
        }
    }
}
