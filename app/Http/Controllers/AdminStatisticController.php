<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Blog;
use App\Models\Coupon;
use App\Models\User;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminStatisticController extends Controller
{
    /**
     * Get admin statistics data.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            // Count active users
            $activeUsersCount = User::where('is_blocked', false)->count();

            // Count total orders
            $totalOrdersCount = Order::count();

            // Calculate total payment amount
            $totalPaymentAmount = Payment::where('status', 'completed')->sum('amount');

            // Count total number of products
            $totalProductsCount = Product::count();

            return ApiResponse::sendResponse([
                'active_users' => $activeUsersCount,
                'total_orders' => $totalOrdersCount,
                'total_payment' => $totalPaymentAmount,
                'total_products' => $totalProductsCount,
            ], 'Admin statistics retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch statistics', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Fetch dashboard statistics for admin.
     *
     * @return JsonResponse
     */
    public function getDashboardStats(): JsonResponse
    {
        try {
            // User Growth Over Time (Last 12 months)
            $userGrowth = User::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as date, COUNT(*) as count")
                ->where('created_at', '>=', Carbon::now()->subMonths(12))
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Active vs Inactive Users
            $activeUsers = User::where('is_blocked', false)->count();
            $inactiveUsers = User::where('is_blocked', true)->count();

            // Orders Over Time (Last 12 months)
            $ordersOverTime = Order::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as date, COUNT(*) as count")
                ->where('created_at', '>=', Carbon::now()->subMonths(12))
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Revenue Trends (Last 12 months)
            $revenueTrends = Payment::where('status', 'paid')
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as date, SUM(amount) as total_revenue")
                ->where('created_at', '>=', Carbon::now()->subMonths(12))
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Top Selling Products (Last 12 months)
            $topSellingProducts = Product::select('products.name', DB::raw('SUM(order_items.quantity) as sales_count'))
                ->join('order_items', 'products.id', '=', 'order_items.product_id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.created_at', '>=', Carbon::now()->subMonths(12))
                ->groupBy('products.name')
                ->orderByDesc('sales_count')
                ->limit(5)
                ->get();

            // Discount & Coupon Usage (Last 12 months)
            $couponUsage = Coupon::select('code', DB::raw('COUNT(coupon_users.id) as usage_count'))
                ->join('coupon_users', 'coupons.id', '=', 'coupon_users.coupon_id')
                ->where('coupon_users.created_at', '>=', Carbon::now()->subMonths(12))
                ->groupBy('code')
                ->orderByDesc('usage_count')
                ->limit(5)
                ->get();

            // User Engagement (Reviews & Blog Likes/Comments over time)
            $userEngagement = Blog::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as date, COUNT(*) as engagement_count")
                ->where('created_at', '>=', Carbon::now()->subMonths(12))
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Return data using ApiResponse
            return ApiResponse::sendResponse([
                'user_growth' => $userGrowth,
                'active_vs_inactive' => [
                    'active_users' => $activeUsers,
                    'inactive_users' => $inactiveUsers
                ],
                'orders_over_time' => $ordersOverTime,
                'revenue_trends' => $revenueTrends,
                'top_selling_products' => $topSellingProducts,
                'coupon_usage' => $couponUsage,
                'user_engagement' => $userEngagement
            ], 'Admin dashboard statistics retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch dashboard statistics', ['error' => $e->getMessage()], 500);
        }
    }
}
