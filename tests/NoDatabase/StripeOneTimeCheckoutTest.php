<?php

declare(strict_types=1);

use App\Services\Payment\StripeGateway;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

it('sends the supported Stripe customer field and a valid callback query separator', function (string $buyer, string $customerField, string $callback, string $successUrl): void {
    $client = Mockery::mock(StripeClient::class, [['api_key' => 'sk_test_local']])->makePartial();
    $request = null;
    $client->shouldReceive('request')->once()
        ->with('post', '/v1/checkout/sessions', Mockery::type('array'), null)
        ->andReturnUsing(function (string $method, string $path, array $params, mixed $options) use (&$request): Session {
            $request = $params;

            return Session::constructFrom(['id' => 'cs_test_email_pack', 'url' => 'https://checkout.stripe.test/email-pack']);
        });
    $gateway = new StripeGateway($client);
    $metadata = ['type' => 'platform_credit_purchase', 'purchase_id' => 42];

    $url = $gateway->createOneTimeCheckoutSession($buyer, 100, 'USD', $callback, $metadata);

    expect($url)->toBe('https://checkout.stripe.test/email-pack');
    expect($request)->toHaveKey($customerField);
    expect($request[$customerField])->toBe($buyer);
    expect($request)->not->toHaveKey($customerField === 'customer' ? 'customer_email' : 'customer');
    expect($request['success_url'])->toBe($successUrl);
    expect($request['cancel_url'])->toBe($callback);
    expect($request['mode'])->toBe('payment');
    expect($request['line_items'][0]['price_data']['currency'])->toBe('usd');
    expect($request['line_items'][0]['price_data']['unit_amount'])->toBe(100);
    expect($request['metadata'])->toBe($metadata);
})->with([
    'email with plain callback' => ['buyer@example.com', 'customer_email', 'https://app.test/billing/callback', 'https://app.test/billing/callback?session_id={CHECKOUT_SESSION_ID}'],
    'customer id with plain callback' => ['cus_existing', 'customer', 'https://app.test/billing/callback', 'https://app.test/billing/callback?session_id={CHECKOUT_SESSION_ID}'],
    'email with existing callback query' => ['buyer@example.com', 'customer_email', 'https://app.test/billing/callback?source=store', 'https://app.test/billing/callback?source=store&session_id={CHECKOUT_SESSION_ID}'],
    'customer id with existing callback query' => ['cus_existing', 'customer', 'https://app.test/billing/callback?source=store', 'https://app.test/billing/callback?source=store&session_id={CHECKOUT_SESSION_ID}'],
]);
