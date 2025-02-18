<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Verification Code</title>
    <style>
        body {
            font-family: 'Nunito', sans-serif;
        }
        .footertext {
            font-size: 12px;
        }
        @media (min-width: 640px) {
            .footertext {
                font-size: 16px;
            }
        }
    </style>
</head>
<body style="margin: 0;">
    <div style="display: flex; align-items: center; justify-content: center; flex-direction: column; margin-top: 1.25rem; font-family: Nunito, sans-serif;">
        <section style="max-width: 42rem; background-color: #fff;">
            <header style="padding-top: 1rem; padding-bottom: 1rem; display: flex; justify-content: center; width: 100%;">
                <a href="#">
                    <img src="https://hebbkx1anhila5yf.public.blob.vercel-storage.com/logo-sbpI27lIuCwNpzsxc04jhF9EjMNAhC.png" alt="cam-o2-logo" style="width: 100px; height:100px" />
                </a>
            </header>
            <div style="width: 100%; height: 2px; background-color: #365CCE;"></div>
            <div style="text-align: center; width: 100%; margin-top: 15px;">
                <div style="font-weight: bold; font-size: 25px;">
                    Account Verification Code
                </div>
            </div>
            <main style="text-align: start; padding: 20px;">
                <p>Dear <strong>{{ $name }}</strong>,</p>
                <p style="margin-top: 10px;">
                    Thank you for registering. Please use the verification code below to verify your email address:
                </p>
                <blockquote style="margin-top: 10px; padding: 10px; background-color: #f3f4f6; border-left: 4px solid #365CCE;">
                    <h3 style="color: blue;">{{ $verificationCode }}</h3>
                </blockquote>
                <p>
                    This code will expire in 10 minutes.
                </p>
                <p>
                    If you did not request this, please ignore this email.
                </p>
                <p>
                    Best Regards, <br />
                    <strong>CAM-O2 Team</strong>
                </p>
            </main>
            <footer style="margin-top: 2rem;">
                <div style="background-color: rgba(209, 213, 219, 0.6); height: 200px; display: flex; flex-direction: column; gap: 1.25rem; justify-content: center; align-items: center;">
                    <div style="text-align: center; display: flex; flex-direction: column; gap: 0.75rem;">
                        <h1 style="color: #365cce; font-weight: bold; font-size: 20px; letter-spacing: 2px;">
                            Get in touch
                        </h1>
                        <a href="tel:+855 92 838 609" style="color: #4b5563;">(+855) 92 838 609</a>
                        <a href="mailto:cam-o2@gmail.com" style="color: #4b5563;">cam-o2@gmail.com</a>
                    </div>
                </div>
                <div style="background-color: #365cce; padding: 10px; color: #fff; text-align: center;">
                    <p class="footertext">© 2025 CAM-O2. All Rights Reserved.</p>
                </div>
            </footer>
        </section>
    </div>
</body>
</html>
