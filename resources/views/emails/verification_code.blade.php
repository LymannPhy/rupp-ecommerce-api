<!DOCTYPE html>
<html>
<head>
    <title>Account Verification Code</title>
</head>
<body>
    <h2>Hello {{ $name }},</h2>
    <p>Thank you for registering. Please use the verification code below to verify your email address:</p>
    <h3 style="color: blue;">{{ $verificationCode }}</h3>
    <p>This code will expire in 10 minutes.</p>
    <p>If you did not request this, please ignore this email.</p>
    <br>
    <p>Best Regards,</p>
    <p>RUPP Pos System</p>
</body>
</html>
