<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .cart-item {
            margin-bottom: 20px;
        }

        .checkout-btn {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }

        .checkout-btn:hover {
            background-color: #45a049;
        }

        /* Popup Styles */
        .popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            padding: 20px;
            background-color: white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            z-index: 9999;
        }

        .popup img {
            max-width: 100%;
            height: auto;
        }

        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9998;
        }

        .close-popup {
            position: absolute;
            top: 10px;
            right: 10px;
            cursor: pointer;
            font-size: 20px;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div>
    <h1>Your Cart</h1>
    <div id="cart-items"></div>

    <button class="checkout-btn" id="checkout-btn">Checkout</button>
</div>

<!-- QR Code Popup -->
<div id="qr-popup" class="popup">
    <span id="close-popup" class="close-popup">&times;</span>
    <h2>Scan the QR Code to Complete Payment</h2>
    <img id="qr-code-img" src="" alt="QR Code" />
</div>
<div id="popup-overlay" class="popup-overlay" style="display:none;"></div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const cartItemsContainer = document.getElementById("cart-items");
        const checkoutBtn = document.getElementById("checkout-btn");
        const qrPopup = document.getElementById("qr-popup");
        const qrCodeImg = document.getElementById("qr-code-img");
        const closePopup = document.getElementById("close-popup");
        const popupOverlay = document.getElementById("popup-overlay");

        fetch('api/carts/items')
            .then(response => response.json())
            .then(data => {
                if (data.length === 0) {
                    console.log("No items in the cart.");
                } else {
                    console.log("Cart Items:", data);
                    // Display cart items here
                }
            })
            .catch(error => console.error('Error fetching cart items:', error));

        // Handle checkout button click
        checkoutBtn.addEventListener("click", () => {
            fetch('api/payments/orders/checkout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ /* Add necessary data if any */ })
            })
            .then(response => response.json())
            .then(data => {
                if (data.qrCode) {
                    qrCodeImg.src = data.qrCode;
                    qrPopup.style.display = 'block';
                    popupOverlay.style.display = 'block';
                }
            })
            .catch(error => console.error('Error during checkout:', error));
        });

        // Close the QR popup
        closePopup.addEventListener("click", () => {
            qrPopup.style.display = 'none';
            popupOverlay.style.display = 'none';
        });

        // Close popup if overlay is clicked
        popupOverlay.addEventListener("click", () => {
            qrPopup.style.display = 'none';
            popupOverlay.style.display = 'none';
        });
    });
</script>

</body>
</html>
