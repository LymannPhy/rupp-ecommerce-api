<!DOCTYPE html>
<html>
<head>
    <title>Password Reset Code</title>
</head>
<body>
    <h2>Hello {{ $name }},</h2>
    <p>You have requested to reset your password.</p>
    <p>Your reset code is:</p>
    <h1>{{ $resetCode }}</h1>
    <p>This code will expire in 15 minutes.</p>
    <p>If you did not request this, please ignore this email.</p>
    <br>
    <p>Best Regards,</p>
    <p><strong>Your Company Name</strong></p>
</body>
</html>
