<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ $order['order_code'] }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        :root {
            --primary-color: #0091EA; /* Blue color from logo */
            --secondary-color: #7CB342; /* Green color from logo */
            --accent-color: #64B5F6;
            --text-color: #1f2937;
            --text-light: #6b7280;
            --border-color: #e5e7eb;
            --background-light: #f9fafb;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            color: var(--text-color);
            line-height: 1.5;
            margin: 0;
            padding: 0;
            font-size: 14px;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .invoice-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .company-details {
            flex: 1;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .company-info {
            color: var(--text-light);
            font-size: 13px;
        }
        
        .invoice-info {
            text-align: right;
        }
        
        .invoice-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .invoice-details {
            color: var(--text-light);
            font-size: 13px;
        }
        
        .invoice-details div {
            margin-bottom: 5px;
        }
        
        .customer-grid {
            display: flex;
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .customer-billing, .customer-shipping {
            flex: 1;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        
        .customer-details {
            background-color: var(--background-light);
            padding: 15px;
            border-radius: 5px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            text-align: left;
            padding: 12px 15px;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        tr:nth-child(even) {
            background-color: var(--background-light);
        }
        
        .item-name {
            font-weight: 500;
        }
        
        .text-right {
            text-align: right;
        }
        
        .totals-container {
            display: flex;
            justify-content: flex-end;
        }
        
        .totals-table {
            width: 350px;
        }
        
        .totals-table td {
            padding: 8px 15px;
        }
        
        .totals-table tr:last-child {
            font-weight: 700;
            font-size: 16px;
            background-color: var(--primary-color);
            color: white;
        }
        
        .footer {
            margin-top: 40px;
            text-align: center;
            color: var(--text-light);
            font-size: 12px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
        
        .thank-you {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .payment-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        .status-paid {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .status-pending {
            background-color: #f59e0b;
            color: white;
        }
        
        .status-cancelled {
            background-color: #ef4444;
            color: white;
        }
        
        .discount {
            color: var(--secondary-color);
            font-weight: 500;
        }
        
        .preorder-badge {
            display: inline-block;
            background-color: var(--secondary-color);
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 5px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Invoice Header -->
        <div class="invoice-header">
            <div class="company-details">
                <div class="company-info">
                    <div>123 Business Street, City</div>
                    <div>O2Project@proton.me</div>
                    <div>+855 123 456 789</div>
                </div>
            </div>
            <div class="invoice-info">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-details">
                    <div><strong>Invoice #:</strong> {{ $order['order_code'] }}</div>
                    <div><strong>Date:</strong> {{ $order['created_at'] }}</div>
                    <div>
                        <strong>Status:</strong> 
                        <span class="payment-status {{ strtolower($order['status']) === 'paid' ? 'status-paid' : (strtolower($order['status']) === 'cancelled' ? 'status-cancelled' : 'status-pending') }}">
                            {{ $order['status'] }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Customer Information -->
        <div class="customer-grid">
            <div class="customer-billing">
                <div class="section-title">Billing Information</div>
                <div class="customer-details">
                    <div><strong>Customer:</strong> {{ auth()->user()->name }}</div>
                    <div><strong>Email:</strong> {{ auth()->user()->email }}</div>
                    <div><strong>Phone:</strong> {{ auth()->user()->phone ?? 'N/A' }}</div>
                </div>
            </div>
            <div class="customer-shipping">
                <div class="section-title">Delivery Information</div>
                <div class="customer-details">
                    <div><strong>Method:</strong> {{ $order['delivery_method'] }}</div>
                    <div><strong>Address:</strong> {{ $order['orderDetail']['address'] ?? 'N/A' }}</div>
                    <div><strong>Expected Date:</strong> {{ $order['delivery_date'] }}</div>
                    @if(isset($order['orderDetail']) && isset($order['orderDetail']['province']))
                    <div><strong>Province:</strong> {{ $order['orderDetail']['province']['name'] }}</div>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Order Items -->
        <div class="section-title">Order Items</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 60px">Image</th>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Qty</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order['items'] as $item)
                <tr>
                    <td>
                        <div class="item-name">
                            {{ $item['product_name'] }}
                            @if($item['is_preorder'])
                            <span class="preorder-badge">Pre-order (50% deposit)</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        @if($item['original_price'] != $item['discounted_price'])
                        <div><s>${{ number_format($item['original_price'], 2) }}</s></div>
                        <div class="discount">${{ number_format($item['discounted_price'], 2) }}</div>
                        @else
                        <div>${{ number_format($item['original_price'], 2) }}</div>
                        @endif
                    </td>
                    <td>{{ $item['quantity'] }}</td>
                    <td class="text-right">${{ number_format($item['total_price'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        
        <!-- Order Totals -->
        <div class="totals-container">
            <table class="totals-table">
                <tr>
                    <td>Subtotal:</td>
                    <td class="text-right">${{ number_format($order['sub_total_price'], 2) }}</td>
                </tr>
                <tr>
                    <td>Delivery Fee:</td>
                    <td class="text-right">${{ number_format($order['delivery_price'], 2) }}</td>
                </tr>
                @if($order['coupon'])
                <tr>
                    <td>Coupon ({{ $order['coupon']['code'] }}):</td>
                    <td class="text-right discount">-${{ number_format(($order['coupon']['discount_percentage'] / 100) * $order['sub_total_price'], 2) }}</td>
                </tr>
                @endif
                <tr>
                    <td>Total:</td>
                    <td class="text-right">${{ number_format($order['total_price'], 2) }}</td>
                </tr>
            </table>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <div class="thank-you">Thank You For Your Business!</div>
            <p>If you have any questions about this invoice, please contact our customer service.</p>
            <p><strong>Note:</strong> Pre-order items are charged at 50% of the original price as a deposit.</p>
            <p>&copy; {{ date('Y') }} CAM-O2. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
