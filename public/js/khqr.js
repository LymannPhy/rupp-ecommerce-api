document.addEventListener("DOMContentLoaded", function () {
    const KHQR = typeof BakongKHQR !== 'undefined' ? BakongKHQR : null;

    if (KHQR) {
        const data = KHQR.khqrData;
        const info = KHQR.IndividualInfo;

        const optionalData = {
            currency: data.currency.usd,
            amount: this.requestStorageAccess.amount,
            mobileNumber: "855315958866",
            storeLabel: "CAM-02",
            terminalLabel: "Cashier_1",
            purposeOfTransaction: "online payment",
            languagePreference: "km",
            merchantNameAlternateLanguage: "ភី​ លីម៉ាន់",
            merchantCityAlternateLanguage: "បាត់ដំបង",
            upiMerchantAccount: "0001034400010344ABCDEFGHJIKLMNO"
        };

        const individualInfo = new info("phy_lymann@aclb", "Phy Lymann", "PHNOM PENH", optionalData);
        const khqrInstance = new KHQR.BakongKHQR();
        const individual = khqrInstance.generateIndividual(individualInfo);

        console.log("qr:" + individual.data.qr);
        console.log("md5:" + individual.data.md5);

        // Function to display QR code in the modal
        const displayQRCode = () => {
            if (individual && individual.data && individual.data.qr) {
                const qrCodeCanvas = document.getElementById("qrCodeCanvas");

                console.log("QR Code Generation Data:", individualInfo);

                // Generate the QR code onto the canvas
                QRCode.toCanvas(qrCodeCanvas, individual.data.qr, function (error) {
                    if (error) console.error(error);
                });

                // Show the modal
                const qrCodeModal = new bootstrap.Modal(document.getElementById("qrCodeModal"));
                qrCodeModal.show();
            } else {
                console.error("QR code data is not available.");
            }
        };

        // Attach event listeners for the Checkout button
        const checkoutButton = document.getElementById("checkout");
        if (checkoutButton) {
            checkoutButton.addEventListener("click", displayQRCode);
        }

    } else {
        console.error("BakongKHQR or its components are not loaded or defined.");
    }
});