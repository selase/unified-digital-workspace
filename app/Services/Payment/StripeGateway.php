<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;

final class StripeGateway implements PaymentGateway
{
    public function __construct(
        private \Stripe\StripeClient $stripe,
        private ?array $config = null
    ) {
        if ($this->config) {
            $this->stripe = new \Stripe\StripeClient($this->config['secret_key']);
        }
    }

    public function createCustomer(string $email, string $name): string
    {
        $customer = $this->stripe->customers->create([
            'email' => $email,
            'name' => $name,
        ]);

        return $customer->id;
    }

    public function createCheckoutSession(string $customerId, string $planId, string $redirectUrl): string
    {
        $session = $this->stripe->checkout->sessions->create([
            'customer' => $customerId,
            'line_items' => [
                [
                    'price' => $planId,
                    'quantity' => 1,
                ],
            ],
            'mode' => 'subscription',
            'success_url' => $redirectUrl.'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $redirectUrl,
        ]);

        return $session->url ?? '';
    }

    public function createOneTimeCheckoutSession(string $customerId, int $amount, string $currency, string $redirectUrl, array $metadata = []): string
    {
        $session = $this->stripe->checkout->sessions->create([
            'customer' => $customerId,
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => [
                            'name' => $metadata['description'] ?? 'Payment',
                        ],
                        'unit_amount' => $amount, // cents
                    ],
                    'quantity' => 1,
                ],
            ],
            'mode' => 'payment',
            'metadata' => $metadata,
            'success_url' => $redirectUrl.'&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $redirectUrl,
        ]);

        return $session->url ?? '';
    }

    public function charge(string $customerId, int $amount, string $currency, array $options = []): string
    {
        $paymentIntent = $this->stripe->paymentIntents->create([
            'amount' => $amount,
            'currency' => $currency,
            'customer' => $customerId,
            'confirm' => true,
            'payment_method' => $options['payment_method'] ?? 'pm_card_visa', // Simplification
            'return_url' => 'https://example.com/return', // Required for confirm: true
        ]);

        return $paymentIntent->id;
    }

    public function subscriptionDetails(string $subscriptionId): array
    {
        $sub = $this->stripe->subscriptions->retrieve($subscriptionId);

        return [
            'status' => $sub->status,
            'current_period_end' => $sub->current_period_end,
        ];
    }

    public function refund(string $transactionId, ?int $amount = null): string
    {
        $params = ['payment_intent' => $transactionId];
        if ($amount) {
            $params['amount'] = $amount;
        }
        $refund = $this->stripe->refunds->create($params);

        return $refund->id;
    }

    public function verifyTransaction(string $reference): array
    {
        // Handle Checkout Session (cs_) vs Payment Intent (pi_)
        if (str_starts_with($reference, 'cs_')) {
            $session = $this->stripe->checkout->sessions->retrieve($reference);

            return [
                'status' => $session->payment_status === 'paid' ? 'success' : $session->payment_status,
                'metadata' => $session->metadata?->toArray() ?? [],
                'transaction_id' => $session->payment_intent,
                'type' => $session->mode, // 'payment' or 'subscription'
            ];
        }

        $pi = $this->stripe->paymentIntents->retrieve($reference);

        return [
            'status' => $pi->status,
            'metadata' => $pi->metadata?->toArray() ?? [],
            'transaction_id' => $pi->id,
            'type' => 'payment',
        ];
    }
}
