<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
        }
        
        .receipt-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1), 0 6px 12px rgba(0, 0, 0, 0.08);
            padding: 30px;
            margin: 20px 0;
            position: relative;
            transition: box-shadow 0.3s ease;
        }
        
        .receipt-card:hover {
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15), 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            font-size: 26px;
            margin-bottom: 25px;
            color: #333;
            font-weight: 600;
            position: relative;
            padding-bottom: 15px;
            font-family: Georgia, 'Times New Roman', Times, serif;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .info-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
        }
        
        .info-table tr {
            transition: background-color 0.2s;
        }
        
        .info-table tr:hover {
            background-color: #f9f9f9;
        }
        
        .info-table td {
            padding: 16px 10px;
            border-bottom: 1px solid #eee;
        }
        
        .info-table tr:last-child td {
            border-bottom: none;
        }
        
        .info-table td:first-child {
            font-weight: 500;
            color: #555;
            width: 40%;
        }
        
        .info-table td:last-child {
            text-align: right;
            font-weight: 500;
            color: #333;
        }
        
        .success {
            color: #4CAF50;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }
        
        .success i {
            margin-left: 8px;
            font-size: 18px;
        }
        
        .watermark {
            position: absolute;
            top: 20px;
            right: 20px;
            opacity: 0.05;
            font-size: 120px;
            transform: rotate(-20deg);
            color: #4CAF50;
            font-weight: bold;
            z-index: 0;
        }
        .date-location {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
        }
        
        .location {
            color: #9ca3af;
            font-weight: normal;
        }
    </style>
</head>

<body>
    <div class="receipt-card">
        <div class="watermark">PAID</div>
        <h1>Information</h1>
        
        <table class="info-table">
            <tr>
                <td>Status</td>
                <td>
                    <span class="success">
                        Success
                        <i class="fas fa-check-circle"></i>
                    </span>
                </td>
            </tr>
            <tr>
                <td>Sender account ID</td>
                <td>{{ $from_account }}</td>
            </tr>
            <tr>
                <td>Recipient account ID</td>
                <td>{{ $to_account }}</td>
            </tr>
            <tr>
                <td>Amount</td>
                <td>$ {{ $amount }}</td>
            </tr>
            <tr>
                <td>Transaction Date</td>
                <td>
                    <div class="date-location">
                        <div>{{ $payment_date }}</div>
                        <div class="location">{{ $transaction_place }}</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>

</html>