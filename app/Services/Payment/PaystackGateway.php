<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use DomainException;
use Exception;
use Illuminate\Support\Facades\Http;

final class PaystackGateway implements PaymentGateway
{
    private string $baseUrl = 'https://api.paystack.co';

    private string $secret;

    public function __construct(?array $config = null)
    {
        $this->secret = $config['secret_key'] ?? config('services.paystack.secret_key') ?? '';
    }

    public function createCustomer(string $email, string $name): string
    {
        $response = Http::withToken($this->secret)->post("{$this->baseUrl}/customer", [
            'email' => $email,
            'first_name' => $name,
        ])->throw();

        return $response->json('data.customer_code');
    }

    public function createCheckoutSession(string $customerId, string $planId, string $redirectUrl): string
    {
        // 1. Fetch customer email using customerId (Customer Code)
        $customerResponse = Http::withToken($this->secret)->get("{$this->baseUrl}/customer/{$customerId}")->throw();
        $email = $customerResponse->json('data.email');

        // 2. Initialize Transaction
        $response = Http::withToken($this->secret)->post("{$this->baseUrl}/transaction/initialize", [
            'email' => $email,
            'plan' => $planId, // Plan Code
            'callback_url' => $redirectUrl,
        ])->throw();

        return $response->json('data.authorization_url');
    }

    public function createOneTimeCheckoutSession(string $customerId, int $amount, string $currency, string $redirectUrl, array $metadata = []): string
    {
        // Fetch email
        $customerResponse = Http::withToken($this->secret)->get("{$this->baseUrl}/customer/{$customerId}")->throw();
        $email = $customerResponse->json('data.email');

        $response = Http::withToken($this->secret)->post("{$this->baseUrl}/transaction/initialize", [
            'email' => $email,
            'amount' => $amount, // kobo
            'currency' => $currency,
            'callback_url' => $redirectUrl,
            'metadata' => $metadata,
        ])->throw();

        return $response->json('data.authorization_url');
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{reference: string, access_code: string}
     */
    public function createInvoiceCheckout(string $email, int $amount, string $callbackUrl, array $metadata): array
    {
        $response = Http::withToken($this->secret)->post("{$this->baseUrl}/transaction/initialize", [
            'email' => $email,
            'amount' => $amount,
            'currency' => 'GHS',
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
        ])->throw();

        $reference = $response->json('data.reference');
        $code = $response->json('data.access_code');
        if (! is_string($reference) || ! preg_match('/^[A-Za-z0-9_.=-]{1,200}$/D', $reference)
            || ! is_string($code) || ! preg_match('/^[A-Za-z0-9_-]{1,200}$/D', $code)) {
            throw new DomainException('Unable to prepare secure checkout. Please contact billing support.');
        }

        return ['reference' => $reference, 'access_code' => $code];
    }

    public function charge(string $customerId, int $amount, string $currency, array $options = []): string
    {
        // Requires authorization_code for recurring/charge_authorization
        if (! isset($options['authorization_code'])) {
            throw new Exception("Paystack charge requires 'authorization_code' in options.");
        }

        // Fetch email
        $customerResponse = Http::withToken($this->secret)->get("{$this->baseUrl}/customer/{$customerId}")->throw();
        $email = $customerResponse->json('data.email');

        $response = Http::withToken($this->secret)->post("{$this->baseUrl}/transaction/charge_authorization", [
            'email' => $email,
            'amount' => $amount, // kobo
            'authorization_code' => $options['authorization_code'],
            'currency' => $currency,
        ])->throw();

        return $response->json('data.reference');
    }

    public function subscriptionDetails(string $subscriptionId): array
    {
        $response = Http::withToken($this->secret)->get("{$this->baseUrl}/subscription/{$subscriptionId}")->throw();

        return [
            'status' => $response->json('data.status'),
            'current_period_end' => strtotime($response->json('data.next_payment_date')),
        ];
    }

    public function refund(string $transactionId, ?int $amount = null): string
    {
        $payload = ['transaction' => $transactionId];
        if ($amount) {
            $payload['amount'] = $amount;
        }

        $response = Http::withToken($this->secret)->post("{$this->baseUrl}/refund", $payload)->throw();

        return $response->json('data.id') ?? 'pending';
    }

    public function verifyTransaction(string $reference): array
    {
        $response = Http::withToken($this->secret)->timeout(10)->get("{$this->baseUrl}/transaction/verify/{$reference}")->throw();
        $data = $response->json('data');

        return [
            'status' => $data['status'], // 'success', 'failed', 'abandoned'
            'amount' => (int) ($data['amount'] ?? 0),
            'currency' => (string) ($data['currency'] ?? ''),
            'metadata' => $data['metadata'] ?? [],
            'transaction_id' => $data['id'],
            'type' => isset($data['plan']) && $data['plan'] ? 'subscription' : 'payment',
        ];
    }
}
