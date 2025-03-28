<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Response from CAM-O2</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            background-color: #f0f9ff;
        }

        .email-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 40px;
            background-color: #ffffff;
            border-radius: 16px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo img {
            height: 70px;
            width: auto;
        }

        .content {
            text-align: left;
            color: #18181b;
        }

        .greeting {
            font-size: 18px;
            color: #0284c7;
            margin-bottom: 24px;
        }

        .message {
            background-color: #f0f9ff;
            border-radius: 12px;
            padding: 32px;
            margin: 24px 0;
            border: 1px solid #e0f2fe;
        }

        .message p {
            margin: 0 0 16px 0;
            color: #52525b;
            font-size: 16px;
            line-height: 1.8;
        }

        .message p:last-child {
            margin-bottom: 0;
        }

        .signature {
            margin-top: 32px;
            color: #52525b;
        }

        .signature-name {
            font-weight: 600;
            color: #0284c7;
        }

        .footer {
            text-align: center;
            margin-top: 32px;
            padding-top: 32px;
            border-top: 1px solid #e4e4e7;
            font-size: 14px;
            color: #71717a;
        }

        @media screen and (max-width: 480px) {
            .email-container {
                margin: 20px;
                padding: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="logo">
            <img src="https://hebbkx1anhila5yf.public.blob.vercel-storage.com/logo-Zw4NpBL7B93493zWF6bm0pJulLIdWU.png" alt="CAM-O2 Logo">
        </div>
        
        <div class="content">
            <div class="greeting">
                Dear {{ $name }},
            </div>
            
            <div class="message">
                <p>{{ $messageBody }}</p>
                
                <p>If you have any further questions or need additional information, please don't hesitate to reach out to us again.</p>
            </div>
            
            <div class="signature">
                Best regards,<br>
                <span class="signature-name">{{ $senderName }}</span><br>
                CAM-O2 Team
            </div>
        </div>
        
        <div class="footer">
            <p>CAM-O2<br>
            Catalyzing sustainable development through equity, empowerment, and integrity.</p>
        </div>
    </div>
</body>
</html>
