<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Responses\ApiResponse;
use App\Models\Cart;
use App\Models\Order;
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

            // ✅ Calculate Final Total (Cart Total + Delivery Fee)
            $finalTotal = round($totalCartValue + $deliveryFee, 2);

            // ✅ Return Only Required Fields
            return ApiResponse::sendResponse([
                'total_cart_value' => $totalCartValue,
                'delivery_fee' => $deliveryFee,
                'final_total' => $finalTotal,
            ], 'Total amount calculated successfully.');

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to calculate total amount.', ['error' => $e->getMessage()], 500);
        }
    }

}
