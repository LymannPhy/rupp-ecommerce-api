<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Verification Code</title>
    <style>
        /* Reset default styles */
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            background-color: #f0f9ff;
        }

        /* Container styles */
        .email-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 32px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Logo and header styles */
        .logo {
            text-align: center;
            margin-bottom: 24px;
        }

        .logo img {
            height: 80px;
            width: auto;
        }

        /* Content styles */
        .content {
            text-align: center;
            color: #18181b;
        }

        h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #0284c7;
        }

        p {
            font-size: 16px;
            margin-bottom: 24px;
            color: #52525b;
        }

        /* Verification code styles */
        .verification-code {
            background-color: #f0f9ff;
            padding: 24px;
            border-radius: 8px;
            margin: 32px 0;
            border: 2px solid #22c55e;
        }

        .code {
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 8px;
            color: #0284c7;
        }

        /* Security note styles */
        .security-note {
            background-color: #f7fee7;
            border-radius: 8px;
            padding: 16px;
            margin: 24px 0;
            text-align: left;
        }

        .security-note p {
            margin: 0;
            font-size: 14px;
            color: #365314;
        }

        /* Footer styles */
        .footer {
            text-align: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e4e4e7;
            font-size: 14px;
            color: #71717a;
        }

        /* Responsive styles */
        @media screen and (max-width: 480px) {
            .email-container {
                margin: 20px;
                padding: 24px;
            }

            h1 {
                font-size: 20px;
            }

            .code {
                font-size: 24px;
                letter-spacing: 6px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="logo">
            <img src="https://hebbkx1anhila5yf.public.blob.vercel-storage.com/logo-Zw4NpBL7B93493zWF6bm0pJulLIdWU.png" alt="Company Logo">
        </div>
        
        <div class="content">
            <h1>Your New Verification Code</h1>
            <p>As requested, here's your new verification code. The previous code has been deactivated.</p>
            
            <div class="verification-code">
                <div class="code">{{ $resetCode }}</div>
            </div>
            
            <div class="security-note">
                <p><strong>Security Note:</strong> For your protection, this new code will expire in 10 minutes. If you didn't request a new verification code, please secure your account by changing your password immediately.</p>
            </div>
            
            <p>Need help? Contact our support team.</p>
        </div>
        
        <div class="footer">
            <p>This email was sent by CAM-O2<br>
            © 2025 CAM-O2. All rights reserved.</p>
        </div>
    </div>
</body>
</html>