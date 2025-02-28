<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan QR Code</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        }

        body {
            background-color: rgba(0, 0, 0, 0.5);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            animation: modalAppear 0.3s ease-out;
            text-align: center;
        }

        @keyframes modalAppear {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.5rem;
            color: #1a1a1a;
        }

        .qr-code-container {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .qr-code {
            max-width: 250px;
            height: auto;
            border: 5px solid #f3f4f6;
            padding: 10px;
            background-color: white;
        }

        .loading {
            text-align: center;
            color: #6b7280;
        }

        .error {
            text-align: center;
            color: #ef4444;
            padding: 1rem;
        }
    </style>
</head>
<body>
    <div class="modal">
        <div class="modal-header">
            <h1 class="modal-title">Scan QR Code</h1>
        </div>

        <div id="content">
            <div class="loading">Generating QR Code...</div>
        </div>
    </div>

    <script>
        async function fetchQRCode() {
            try {
                const uuid = "991b20cf-064b-47dc-9bdb-104c0c2e757c"; 
                const apiBaseUrl = "http://127.0.0.1:8000/api"; // ✅ Correctly use the passed variable
    
                console.log("Fetching QR Code from:", apiBaseUrl + "/suppliers/" + uuid + "/qr-code");
    
                const response = await fetch(apiBaseUrl + "/suppliers/" + uuid + "/qr-code");
    
                if (!response.ok) {
                    throw new Error(`HTTP Error: ${response.status}`);
                }
    
                const data = await response.json();
    
                if (data.code === 200 && data.data.qr_code) {
                    document.getElementById('content').innerHTML = `
                        <div class="qr-code-container">
                            <img src="${data.data.qr_code}" class="qr-code" alt="QR Code">
                        </div>
                        <p>Scan this QR code to view the supplier profile.</p>
                    `;
                } else {
                    throw new Error("Invalid QR code data received");
                }
            } catch (error) {
                document.getElementById('content').innerHTML = `
                    <div class="error">
                        <p>Error loading QR code. Please try again later.</p>
                    </div>
                `;
                console.error('Error:', error);
            }
        }
    
        fetchQRCode();
    </script>

</body>
</html>
