<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaystackService
{
    private string $secretKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('nis.paystack.secret_key');
        $this->baseUrl = config('nis.paystack.payment_url', 'https://api.paystack.co');
    }

    /**
     * Initialize a payment transaction.
     *
     * @return array ['authorization_url', 'access_code', 'reference']
     */
    public function initialize(array $data): array
    {
        $reference = $data['reference'] ?? $this->generateReference();

        $payload = [
            'email'     => $data['email'],
            'amount'    => (int) ($data['amount'] * 100), // Paystack uses kobo
            'reference' => $reference,
            'currency'  => $data['currency'] ?? 'NGN',
            'callback_url' => $data['callback_url'] ?? config('nis.paystack.callback_url'),
            'metadata'  => $data['metadata'] ?? [],
        ];

        // Add optional channels
        if (!empty($data['channels'])) {
            $payload['channels'] = $data['channels']; // ['card', 'bank', 'ussd', 'qr']
        }

        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transaction/initialize", $payload);

        if (!$response->successful() || !$response->json('status')) {
            throw new \Exception(
                'Paystack initialization failed: ' . ($response->json('message') ?? 'Unknown error')
            );
        }

        $result = $response->json('data');

        return [
            'authorization_url' => $result['authorization_url'],
            'access_code'       => $result['access_code'],
            'reference'         => $result['reference'],
        ];
    }

    /**
     * Verify a transaction by reference.
     *
     * @return array Full transaction data from Paystack
     */
    public function verify(string $reference): array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transaction/verify/{$reference}");

        if (!$response->successful() || !$response->json('status')) {
            throw new \Exception(
                'Paystack verification failed: ' . ($response->json('message') ?? 'Unknown error')
            );
        }

        return $response->json('data');
    }

    /**
     * Get list of banks for bank transfer.
     */
    public function listBanks(string $currency = 'NGN'): array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/bank", ['currency' => $currency]);

        if (!$response->successful()) {
            return [];
        }

        return $response->json('data', []);
    }

    /**
     * Generate unique payment reference.
     */
    public function generateReference(): string
    {
        return 'NIS-' . strtoupper(Str::random(6)) . '-' . time();
    }

    /**
     * Validate Paystack webhook signature.
     */
    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        $computed = hash_hmac('sha512', $payload, $this->secretKey);

        return hash_equals($computed, $signature);
    }
}
