<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="referrer" content="no-referrer">
  <title>Login with Google</title>
  <script src="https://accounts.google.com/gsi/client" async defer></script>
  <style>
    /* Reset and base styles */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
    }

    body {
      background-color: #f5f5f5;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      padding: 1rem;
    }

    /* Card container */
    .card {
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      width: 100%;
      max-width: 400px;
      overflow: hidden;
    }

    /* Card header */
    .card-header {
      padding: 1.5rem;
      text-align: center;
    }

    .card-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: #333;
      margin-bottom: 0.5rem;
    }

    .card-description {
      color: #666;
      font-size: 0.875rem;
    }

    /* Card content */
    .card-content {
      padding: 0 1.5rem 1.5rem;
    }

    /* Form styles */
    .form-group {
      margin-bottom: 1rem;
    }

    label {
      display: block;
      margin-bottom: 0.5rem;
      font-size: 0.875rem;
      font-weight: 500;
      color: #333;
    }

    .input-wrapper {
      position: relative;
    }

    .input-icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      width: 16px;
      height: 16px;
      color: #666;
    }

    input {
      width: 100%;
      padding: 0.75rem 0.75rem 0.75rem 2.5rem;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 0.875rem;
      transition: border-color 0.2s;
    }

    input:focus {
      outline: none;
      border-color: #4f46e5;
      box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
    }

    .password-wrapper {
      position: relative;
    }

    .forgot-password {
      position: absolute;
      right: 0;
      top: -1.5rem;
      font-size: 0.75rem;
      color: #4f46e5;
      text-decoration: none;
    }

    .forgot-password:hover {
      text-decoration: underline;
    }

    /* Button styles */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      padding: 0.75rem 1rem;
      border-radius: 4px;
      font-size: 0.875rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
      border: none;
    }

    .btn-primary {
      background-color: #4f46e5;
      color: white;
    }

    .btn-primary:hover {
      background-color: #4338ca;
    }

    .btn-outline {
      background-color: white;
      color: #333;
      border: 1px solid #ddd;
    }

    .btn-outline:hover {
      background-color: #f9fafb;
    }

    /* Divider */
    .divider {
      display: flex;
      align-items: center;
      margin: 1.5rem 0;
    }

    .divider-line {
      flex-grow: 1;
      height: 1px;
      background-color: #ddd;
    }

    .divider-text {
      padding: 0 0.75rem;
      color: #666;
      font-size: 0.75rem;
    }

    /* Google icon */
    .google-icon {
      margin-right: 0.5rem;
      width: 18px;
      height: 18px;
    }

    /* Card footer */
    .card-footer {
      padding: 1.5rem;
      text-align: center;
      border-top: 1px solid #eee;
    }

    .card-footer p {
      font-size: 0.875rem;
      color: #666;
    }

    .signup-link {
      color: #4f46e5;
      text-decoration: none;
    }

    .signup-link:hover {
      text-decoration: underline;
    }

    /* Responsive adjustments */
    @media (max-width: 480px) {
      .card {
        box-shadow: none;
      }
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="card-header">
      <h1 class="card-title">Welcome back</h1>
      <p class="card-description">Sign in to your account to continue</p>
    </div>
    <div class="card-content">
      <form action="#" method="POST">
        <div class="form-group">
          <label for="email">Email</label>
          <div class="input-wrapper">
            <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
              <polyline points="22,6 12,13 2,6"></polyline>
            </svg>
            <input type="email" id="email" name="email" placeholder="name@example.com" required>
          </div>
        </div>
        <div class="form-group">
          <div class="password-wrapper">
            <label for="password">Password</label>
          </div>
          <div class="input-wrapper">
            <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <input type="password" id="password" name="password" required>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Sign in</button>
      </form>


      <div class="divider">
        <div class="divider-line"></div>
        <span class="divider-text">OR</span>
        <div class="divider-line"></div>
      </div>

      <!-- Google Sign-In Button -->
      <div id="g_id_onload"
        data-client_id="426697838308-qihuet2qbmidfrr2ih22tp72803dhth0.apps.googleusercontent.com"
        data-context="signin"
        data-ux_mode="popup"
        data-callback="handleCredentialResponse"
        data-auto_select="false"
        data-itp_support="true">
    </div>

      <button type="button" class="btn btn-outline" onclick="triggerGoogleSignIn()">
        <svg class="google-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 488 512">
          <path fill="currentColor" d="M488 261.8C488 403.3 391.1 504 248 504 110.8 504 0 393.2 0 256S110.8 8 248 8c66.8 0 123 24.5 166.3 64.9l-67.5 64.9C258.5 52.6 94.3 116.6 94.3 256c0 86.5 69.1 156.6 153.7 156.6 98.2 0 135-70.4 140.8-106.9H248v-85.3h236.1c2.3 12.7 3.9 24.9 3.9 41.4z"></path>
        </svg>
        Sign in with Google
      </button>
    </div>
    <div class="card-footer">
      <p>Don't have an account? <a href="#" class="signup-link">Sign up</a></p>
    </div>
  </div>

  <script>
    window.triggerGoogleSignIn = function () {
        google.accounts.id.prompt(); // Show Google Login Pop-up
    };

    window.handleCredentialResponse = function (response) {
        console.log("Google Token:", response.credential); // Google JWT Token

        // Send token to Laravel backend
        fetch("http://127.0.0.1:8000/api/auth/google", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Access-Control-Allow-Origin": "*"
            },
            body: JSON.stringify({ provider: "google", access_token: response.credential })
        })
        .then(res => res.json())
        .then(data => {
            if (data.access_token) {
                alert("Login Successful! Token: " + data.access_token);
                localStorage.setItem("authToken", data.access_token); 
            } else {
                alert("Login failed: " + data.error);
            }
        })
        .catch(err => console.error("Error:", err));
    };
</script>

</body>
</html>