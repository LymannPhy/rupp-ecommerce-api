<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KhqrService {
    private string $bakongUrl;
    private string $bakongToken;

    public function __construct() {
        $this->bakongUrl = config('services.bakong.base_url');
        $this->bakongToken = config('services.bakong.token');
    }

    public function generateQrCode(Payment $payment): array {
        $merchantInfo = [
            "bakongAccountId" => "phy_lymann@aclb",
            "acquiringBank" => "Acleda Bank Plc.",
            "merchantName" => "CAM-O2",
            "merchantCity" => "Phnom Penh",
            "amount" => $payment->amount,
            "currency" => "USD",
            "billNumber" => "CAM-O2" . now()->timestamp,
            "storeLabel" => "CAM-O2",
            "terminalLabel" => "POS-01"
        ];

        $response = Http::post("$this->bakongUrl/generate_merchant", $merchantInfo);
        $data = $response->json();

        if ($response->ok() && $data['KHQRStatus']['code'] == 0) {
            $qrCode = $data['data']['qr'];
            $md5Hash = $data['data']['md5'];

            $payment->update([
                'qr_code' => $qrCode,
                'md5_hash' => $md5Hash
            ]);

            return ['qr_code' => $qrCode, 'md5' => $md5Hash];
        }

        Log::error("QR Code generation failed: " . json_encode($data));
        return ['error' => 'Failed to generate QR code'];
    }

    public function checkPaymentStatus(Payment $payment): bool {
        $response = Http::withToken($this->bakongToken)
            ->post("$this->bakongUrl/check_transaction_by_md5", ['md5' => $payment->md5_hash]);

        $data = $response->json();

        if ($response->ok() && $data['responseCode'] == 0) {
            $payment->update(['status' => 'completed']);
            return true;
        }

        return false;
    }
}
