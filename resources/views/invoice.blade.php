<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f5f5;
            padding: 20px;
            color: #333;
        }

        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            padding: 40px;
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .company-details {
            flex: 1;
            display: flex;
            align-items: start;
            gap: 20px;
        }

        .logo {
            width: 100px;
            height: 100px;
            object-fit: contain;
        }

        .company-info h1 {
            color: #2196F3;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .invoice-info {
            text-align: right;
        }

        .invoice-info h2 {
            font-size: 24px;
            color: #2196F3;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }

        .paid-stamp {
            background-color: #4CAF50;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 14px;
            font-weight: 500;
        }

        .client-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }

        .detail-group h3 {
            font-size: 16px;
            color: #4CAF50;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-group p {
            margin-bottom: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        th {
            background-color: #f8fafc;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #2196F3;
            border-bottom: 2px solid #e5e7eb;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }

        .totals {
            float: right;
            width: 300px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }

        .total-row.final {
            font-weight: bold;
            font-size: 18px;
            border-top: 2px solid #e5e7eb;
            margin-top: 8px;
            padding-top: 12px;
            color: #2196F3;
        }

        .footer {
            clear: both;
            margin-top: 40px;
            text-align: center;
            color: #666;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }

        @media (max-width: 768px) {
            .invoice-container {
                padding: 20px;
            }

            .invoice-header {
                flex-direction: column;
                gap: 20px;
            }

            .company-details {
                flex-direction: column;
                text-align: center;
            }

            .logo {
                margin: 0 auto;
            }

            .invoice-info {
                text-align: center;
            }

            .client-details {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .totals {
                float: none;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="invoice-header">
            <div class="company-details">
                <img src="https://hebbkx1anhila5yf.public.blob.vercel-storage.com/logo-aPyvWNiDugjOYhPwHsR0pWRdzf64II.png" alt="Company Logo" class="logo">
                <div class="company-info">
                    <h1>CAM-O2</h1>
                    <p>123 Business Street</p>
                    <p>Phnom Penh Thmey, Sen Sok, Phnom Penh</p>
                    <p>Phone: (+855) 92 838 609</p>
                    <p>Email: camo2.info88@gmail.com</p>
                </div>
            </div>
            <div class="invoice-info">
                <h2>INVOICE <span class="paid-stamp">PAID</span></h2>
                <p><strong>Invoice #:</strong> {{ $order->id }}</p>
                <p><strong>Date:</strong> {{ $order->created_at->format('F d, Y') }}</p>
                <p><strong>Payment Date:</strong> {{ $order->updated_at->format('F d, Y') }}</p>
            </div>
        </div>

        <div class="client-details">
            <div class="detail-group">
                <h3>Bill To</h3>
                <p><strong>{{ $order->orderDetail->email }}</strong></p>
                <p>Phone: {{ $order->orderDetail->phone_number }}</p>
                <p>Province: {{ $order->orderDetail->province->name }}</p>
            </div>
            <div class="detail-group">
                <h3>Delivery Details</h3>
                <p><strong>Delivery Address:</strong></p>
                <p>{{ $order->orderDetail->province->name }}</p>
                <p><strong>Delivery Date:</strong> {{ now()->addDays(2)->format('F d, Y') }}</p>
                <p><strong>Delivery Method:</strong> Standard Delivery</p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->orderItems as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>${{ number_format($item->price, 2) }}</td>
                    <td>${{ number_format($item->quantity * $item->price, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>${{ number_format($order->total_price - $order->orderDetail->province->delivery_fee, 2) }}</span>
            </div>
            <div class="total-row">
                <span>Delivery Fee:</span>
                <span>${{ number_format($order->orderDetail->province->delivery_fee, 2) }}</span>
            </div>
            <div class="total-row final">
                <span>Total Paid:</span>
                <span>${{ number_format($order->total_price, 2) }}</span>
            </div>
        </div>

        <div class="footer">
            <p>Thank you for your payment!</p>
            <p>This invoice serves as proof of payment for the items listed above.</p>
            <p>For any questions about your order or delivery, please contact our customer service department.</p>
        </div>
    </div>
</body>
</html>