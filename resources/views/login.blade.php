<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CAM-O2 Login</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    
    html, body {
      height: 100%;
      width: 100%;
    }
    
    body {
      font-family: 'Roboto', Arial, sans-serif;
      background-color: #f5f5f5;
      display: flex;
      justify-content: center;
      align-items: center;
    }
    
    .login-container {
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      width: 100%;
      max-width: 360px;
      padding: 32px;
      margin: 16px;
    }
    
    .logo {
      width: 80px;
      height: 80px;
      margin: 0 auto 16px;
      display: block;
    }
    
    .company-name {
      color: #2196F3;
      font-size: 24px;
      font-weight: 500;
      text-align: center;
      margin-bottom: 32px;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 8px;
      color: #333;
      font-size: 14px;
    }
    
    .form-group input {
      width: 100%;
      padding: 8px 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 14px;
    }
    
    .form-group input:focus {
      outline: none;
      border-color: #2196F3;
    }
    
    .login-btn {
      width: 100%;
      padding: 10px;
      background-color: #2196F3;
      color: white;
      border: none;
      border-radius: 4px;
      font-size: 16px;
      cursor: pointer;
      margin-bottom: 24px;
    }
    
    .login-btn:hover {
      background-color: #1976D2;
    }
    
    .divider {
      text-align: center;
      position: relative;
      margin: 24px 0;
    }
    
    .divider::before,
    .divider::after {
      content: "";
      position: absolute;
      top: 50%;
      width: 45%;
      height: 1px;
      background-color: #ddd;
    }
    
    .divider::before {
      left: 0;
    }
    
    .divider::after {
      right: 0;
    }
    
    .google-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 4px;
      background-color: white;
      color: #333;
      font-size: 14px;
      cursor: pointer;
      gap: 8px;
    }
    
    .google-btn:hover {
      background-color: #f8f9fa;
    }
    
    .footer {
      text-align: center;
      margin-top: 24px;
      font-size: 14px;
      color: #666;
    }
    
    .footer a {
      color: #2196F3;
      text-decoration: none;
    }
    
    .footer a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <img src="https://hebbkx1anhila5yf.public.blob.vercel-storage.com/IMG_20250208_083848_904.jpg-TTq5qhqeEuVJKzC7qgOfZIZTx5R26c.jpeg" alt="CAM-O2 Logo" class="logo">
    <h1 class="company-name">CAM-O2</h1>
    
    <form id="loginForm">
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="Enter your email" required>
      </div>
      
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Enter your password" required>
      </div>
      
      <button type="submit" class="login-btn">Log In</button>
    </form>
    
    <div class="divider">OR</div>
    
    <button id="googleLogin" class="google-btn">
      <svg width="18" height="18" viewBox="0 0 18 18">
        <path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z"/>
        <path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.258c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332C2.438 15.983 5.482 18 9 18z"/>
        <path fill="#FBBC05" d="M3.964 10.707c-.18-.54-.282-1.117-.282-1.707s.102-1.167.282-1.707V4.961H.957C.347 6.192 0 7.556 0 9s.348 2.808.957 4.039l3.007-2.332z"/>
        <path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0 5.482 0 2.438 2.017.957 4.961L3.964 7.293C4.672 5.166 6.656 3.58 9 3.58z"/>
      </svg>
      Sign in with Google
    </button>
    
    <div class="footer">
      Don't have an account? <a href="#">Sign up</a>
    </div>
  </div>

  <script>
    document.getElementById('googleLogin').addEventListener('click', function(event) {
      event.preventDefault();
      window.location.href = "{{ url('api/auth/google') }}";
    });
    
    document.getElementById('loginForm').addEventListener('submit', function(event) {
      event.preventDefault();
      // Add your login form submission logic here
    });
  </script>
</body>
</html>