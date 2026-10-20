<?php

declare(strict_types=1);

use App\Events\InvoicePaymentReceived;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Services\Billing\InvoiceCheckoutService;
use App\Services\Billing\InvoicePaymentLinkService;
use App\Services\Billing\InvoiceReceiptService;
use App\Services\Billing\PaymentFulfillmentService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Event::fake([InvoicePaymentReceived::class]);
    config(['services.paystack.secret_key' => 'invoice-test-secret', 'services.stripe.secret' => 'stripe-test-secret']);
    $this->billingTenant = Tenant::factory()->create([
        'email' => 'default@example.test',
        'meta' => ['billing_email' => 'accounts@example.test'],
    ]);
    $this->invoice = Invoice::query()->create([
        'tenant_id' => $this->billingTenant->id,
        'number' => 'INV-'.Illuminate\Support\Str::uuid(),
        'period_start' => now()->subMonth()->startOfMonth(),
        'period_end' => now()->subMonth()->endOfMonth(),
        'due_at' => now()->addDays(7),
        'status' => Invoice::STATUS_ISSUED,
        'currency' => 'GHS',
        'subtotal' => '100.00',
        'tax_total' => '0.00',
        'total' => '100.00',
    ]);
    $this->verifiedPayment = [
        'status' => 'success',
        'id' => 7654321,
        'amount' => 10000,
        'currency' => 'GHS',
        'metadata' => ['invoice_id' => $this->invoice->id, 'tenant_id' => $this->billingTenant->id],
    ];
});

function fulfillLifecycleInvoice(Invoice $invoice, string $reference = '7654321', ?int $amount = 10000, ?string $currency = 'GHS', ?array $metadata = null): Invoice
{
    return app(PaymentFulfillmentService::class)->fulfillInvoicePayment($invoice, $reference, 'paystack', $amount, $currency,
        $metadata ?? ['invoice_id' => $invoice->id, 'tenant_id' => $invoice->tenant_id]);
}

it('records one immutable receipt and transaction for a verified invoice payment replay', function (): void {
    $paid = fulfillLifecycleInvoice($this->invoice);
    Event::assertDispatchedTimes(InvoicePaymentReceived::class, 1);
    $receipt = $paid->meta['receipt'];
    $this->travel(1)->days();
    $replayed = fulfillLifecycleInvoice($this->invoice);

    expect($replayed->status)->toBe(Invoice::STATUS_PAID)
        ->and($replayed->meta['receipt'])->toEqual($receipt)
        ->and($replayed->meta['receipt_number'])->toBe($receipt['number'])
        ->and(Transaction::query()->where('provider', 'paystack')->where('provider_transaction_id', '7654321')->count())->toBe(1);
    Event::assertDispatchedTimes(InvoicePaymentReceived::class, 1);
});

it('rejects a mismatched verified amount currency or tenant without recording payment', function (string $kind): void {
    $metadata = ['invoice_id' => $this->invoice->id, 'tenant_id' => $this->invoice->tenant_id];
    if ($kind === 'tenant') {
        $metadata['tenant_id'] = 'unrelated-tenant';
    }
    if ($kind === 'invoice') {
        $metadata['invoice_id'] = 'unrelated-invoice';
    }
    expect(fn () => fulfillLifecycleInvoice($this->invoice, 'mismatch',
        $kind === 'underpayment' ? 9999 : ($kind === 'overpayment' ? 10001 : 10000),
        $kind === 'currency' ? 'USD' : 'GHS', $metadata))->toThrow(DomainException::class);
    expect($this->invoice->fresh()->status)->toBe(Invoice::STATUS_ISSUED)
        ->and(Transaction::query()->where('provider_transaction_id', 'mismatch')->exists())->toBeFalse();
    Event::assertNotDispatched(InvoicePaymentReceived::class);
})->with(['underpayment', 'overpayment', 'currency', 'tenant', 'invoice']);

it('forbids draft and void payment and portal access', function (string $status): void {
    $this->invoice->update(['status' => $status]);
    expect(fn () => fulfillLifecycleInvoice($this->invoice))->toThrow(DomainException::class);
    $this->get(app(InvoicePaymentLinkService::class)->url($this->invoice))->assertNotFound();
})->with([Invoice::STATUS_DRAFT, Invoice::STATUS_VOID]);

it('accepts an overdue invoice', function (): void {
    $this->invoice->update(['status' => Invoice::STATUS_OVERDUE]);
    expect(fulfillLifecycleInvoice($this->invoice)->status)->toBe(Invoice::STATUS_PAID);
});

it('prevents a provider transaction paying another invoice across tenants', function (): void {
    fulfillLifecycleInvoice($this->invoice);
    $other = $this->invoice->replicate();
    $other->number = 'OTHER-INVOICE';
    $other->status = Invoice::STATUS_ISSUED;
    $other->tenant_id = Tenant::factory()->create()->id;
    $other->save();
    expect(fn () => fulfillLifecycleInvoice($other))->toThrow(DomainException::class);
    expect($other->fresh()->status)->toBe(Invoice::STATUS_ISSUED);
});

it('signs the canonical application host even when the active request is a tenant', function (): void {
    config(['app.url' => 'https://billing.example.test']);
    URL::forceRootUrl('https://tenant.example.test');
    try {
        $url = app(InvoicePaymentLinkService::class)->url($this->invoice);
        expect(parse_url($url, PHP_URL_HOST))->toBe('billing.example.test');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        expect((int) $query['expires'])->toBe(now()->addDays(30)->timestamp);
    } finally {
        URL::forceRootUrl(null);
    }
});

it('allows a billing contact without an account to see a signed invoice', function (): void {
    $this->get(app(InvoicePaymentLinkService::class)->url($this->invoice))
        ->assertOk()->assertSee($this->invoice->number)->assertSee('Pay GHS 100.00')
        ->assertHeader('Referrer-Policy', 'no-referrer');
});

it('rejects missing expired and tampered portal signatures', function (): void {
    $this->get(route('billing.payment.show', $this->invoice))->assertForbidden();
    $url = app(InvoicePaymentLinkService::class)->url($this->invoice);
    $this->get($url.'&extra=tampered')->assertForbidden();
    $this->travel(31)->days();
    $this->get($url)->assertForbidden();
});

it('initializes exact GHS hosted checkout for the billing contact with a verifying callback', function (): void {
    Http::fake(['api.paystack.co/transaction/initialize' => Http::response([
        'status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/invoice-test'],
    ])]);
    $tenantMeta = $this->billingTenant->meta;
    $url = app(InvoicePaymentLinkService::class)->signedUrl($this->invoice, 'billing.payment.pay');
    $this->post($url)->assertRedirect('https://checkout.paystack.com/invoice-test');
    Http::assertSent(fn ($request) => $request['amount'] === 10000 && $request['currency'] === 'GHS'
        && $request['email'] === 'accounts@example.test'
        && $request['metadata']['tenant_id'] === $this->billingTenant->id
        && $request['metadata']['invoice_id'] === $this->invoice->id
        && str_contains($request['callback_url'], '/billing/payment-return/paystack'));
    expect($this->billingTenant->fresh()->meta)->toBe($tenantMeta);
});

it('requires a signature before starting checkout', function (): void {
    $this->post(route('billing.payment.pay', $this->invoice))->assertForbidden();
    Http::assertNothingSent();
});

it('verifies an unauthenticated return and ignores a supplied redirect destination', function (): void {
    Http::fake(['api.paystack.co/transaction/verify/return-ref' => Http::response(['data' => $this->verifiedPayment])]);
    $this->get(route('billing.payment.callback', ['provider' => 'paystack', 'reference' => 'return-ref', 'redirect' => 'https://evil.example']))
        ->assertRedirect(app(InvoicePaymentLinkService::class)->url($this->invoice));
    expect($this->invoice->fresh()->status)->toBe(Invoice::STATUS_PAID);
});

it('does not accept an unconfirmed or mismatched callback payment', function (string $kind): void {
    $data = $this->verifiedPayment;
    $data[$kind === 'status' ? 'status' : 'amount'] = $kind === 'status' ? 'failed' : 1;
    Http::fake(['api.paystack.co/transaction/verify/return-ref' => Http::response(['data' => $data])]);
    $this->get(route('billing.payment.callback', ['provider' => 'paystack', 'reference' => 'return-ref']))->assertUnprocessable();
    expect($this->invoice->fresh()->status)->toBe(Invoice::STATUS_ISSUED);
})->with(['status', 'amount']);

it('rejects unsigned platform webhooks before contacting Paystack', function (): void {
    $this->postJson(route('webhooks.paystack'), ['event' => 'charge.success'])->assertBadRequest();
    Http::assertNothingSent();
});

it('verifies a platform webhook server side and handles replay', function (): void {
    Http::fake(['api.paystack.co/transaction/verify/webhook-ref' => Http::response(['data' => $this->verifiedPayment])]);
    $payload = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'webhook-ref', 'amount' => 1]]);
    $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $payload, 'invoice-test-secret')];
    $this->call('POST', route('webhooks.paystack'), [], [], [], $headers, $payload)->assertOk();
    $this->call('POST', route('webhooks.paystack'), [], [], [], $headers, $payload)->assertOk();
    expect($this->invoice->fresh()->status)->toBe(Invoice::STATUS_PAID)
        ->and(Transaction::query()->where('provider_transaction_id', '7654321')->count())->toBe(1);
});

it('rejects webhook tenant mismatches despite a valid webhook signature', function (): void {
    $data = $this->verifiedPayment;
    $data['metadata']['tenant_id'] = 'wrong-tenant';
    Http::fake(['api.paystack.co/transaction/verify/webhook-ref' => Http::response(['data' => $data])]);
    $payload = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'webhook-ref']]);
    $this->call('POST', route('webhooks.paystack'), [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $payload, 'invoice-test-secret'),
    ], $payload)->assertUnprocessable();
    expect($this->invoice->fresh()->status)->toBe(Invoice::STATUS_ISSUED);
});

it('downloads signed invoice and verified receipt PDFs', function (): void {
    $links = app(InvoicePaymentLinkService::class);
    $this->get($links->signedUrl($this->invoice, 'billing.payment.receipt'))->assertNotFound();
    $this->get($links->signedUrl($this->invoice, 'billing.payment.invoice'))->assertOk()->assertHeader('content-type', 'application/pdf');
    fulfillLifecycleInvoice($this->invoice);
    $this->get($links->signedUrl($this->invoice, 'billing.payment.receipt'))->assertOk()->assertHeader('content-type', 'application/pdf');
    expect(app(InvoiceReceiptService::class)->output($this->invoice->fresh()))->toStartWith('%PDF');
    $this->get($links->url($this->invoice))->assertOk()->assertSee('Download payment receipt')->assertDontSee('Pay GHS');
});

it('does not fabricate receipts for historical paid invoices', function (): void {
    $this->invoice->update(['status' => Invoice::STATUS_PAID]);
    expect(fn () => app(InvoiceReceiptService::class)->output($this->invoice))->toThrow(DomainException::class);
});

it('falls back to tenant email without altering tenant customer details', function (): void {
    $this->billingTenant->update(['meta' => []]);
    Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['data' => ['authorization_url' => 'https://checkout.paystack.com/test']])]);
    app(InvoiceCheckoutService::class)->checkout($this->invoice->fresh());
    Http::assertSent(fn ($request) => $request['email'] === 'default@example.test');
});

it('does not dispatch receipt events or persist payment when the outer transaction rolls back', function (): void {
    $connection = Illuminate\Support\Facades\DB::connection('landlord');
    $connection->beginTransaction();
    try {
        fulfillLifecycleInvoice($this->invoice);
        Event::assertNotDispatched(InvoicePaymentReceived::class);
    } finally {
        $connection->rollBack();
    }
    Event::assertNotDispatched(InvoicePaymentReceived::class);
    expect($this->invoice->fresh()->status)->toBe(Invoice::STATUS_ISSUED)
        ->and(Transaction::query()->where('provider_transaction_id', '7654321')->exists())->toBeFalse();
});

it('never opens another checkout for a paid invoice', function (): void {
    fulfillLifecycleInvoice($this->invoice);
    $links = app(InvoicePaymentLinkService::class);
    $this->post($links->signedUrl($this->invoice, 'billing.payment.pay'))->assertRedirect($links->url($this->invoice));
    Http::assertNothingSent();
});

it('requires signed invoice and receipt downloads', function (): void {
    $this->get(route('billing.payment.invoice', $this->invoice))->assertForbidden();
    $this->get(route('billing.payment.receipt', $this->invoice))->assertForbidden();
});

it('keeps USD invoices on Stripe with exact totals and tenant metadata', function (): void {
    $this->invoice->update(['currency' => 'USD']);
    $sessions = Mockery::mock(Stripe\Service\Checkout\SessionService::class);
    $sessions->shouldReceive('create')->once()->with(Mockery::on(fn (array $payload): bool => $payload['customer_email'] === 'accounts@example.test'
        && $payload['line_items'][0]['price_data']['unit_amount'] === 10000
        && $payload['line_items'][0]['price_data']['currency'] === 'usd'
        && $payload['metadata']['tenant_id'] === $this->billingTenant->id
        && str_contains($payload['success_url'], '/billing/payment-return/stripe?session_id=')))
        ->andReturn(Stripe\Checkout\Session::constructFrom(['url' => 'https://checkout.stripe.com/test']));
    $stripe = Mockery::mock(Stripe\StripeClient::class);
    $stripe->shouldReceive('getService')->with('checkout')->andReturn((object) ['sessions' => $sessions]);
    app()->instance(Stripe\StripeClient::class, $stripe);
    expect(app(InvoiceCheckoutService::class)->checkout($this->invoice->fresh()))->toBe('https://checkout.stripe.com/test');
    Http::assertNothingSent();
});

it('verifies USD Stripe callbacks before redirecting to a signed receipt portal', function (): void {
    $this->invoice->update(['currency' => 'USD']);
    $sessions = Mockery::mock(Stripe\Service\Checkout\SessionService::class);
    $sessions->shouldReceive('retrieve')->once()->with('cs_invoice')->andReturn(Stripe\Checkout\Session::constructFrom([
        'payment_status' => 'paid', 'amount_total' => 10000, 'currency' => 'usd',
        'payment_intent' => 'pi_invoice', 'mode' => 'payment',
        'metadata' => ['invoice_id' => $this->invoice->id, 'tenant_id' => $this->invoice->tenant_id],
    ]));
    $stripe = Mockery::mock(Stripe\StripeClient::class);
    $stripe->shouldReceive('getService')->with('checkout')->andReturn((object) ['sessions' => $sessions]);
    app()->instance(Stripe\StripeClient::class, $stripe);
    $this->get(route('billing.payment.callback', ['provider' => 'stripe', 'session_id' => 'cs_invoice']))
        ->assertRedirect(app(InvoicePaymentLinkService::class)->url($this->invoice));
    expect($this->invoice->fresh()->status)->toBe(Invoice::STATUS_PAID)
        ->and($this->invoice->fresh()->meta['provider'])->toBe('stripe');
});

it('rejects Stripe checkout webhooks until payment is actually paid', function (string $paymentStatus): void {
    config(['services.stripe.webhook_secret' => 'whsec_invoice_test']);
    $this->invoice->update(['currency' => 'USD']);
    $payload = json_encode([
        'id' => 'evt_invoice', 'object' => 'event', 'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => 'cs_invoice', 'object' => 'checkout.session', 'payment_status' => $paymentStatus,
            'payment_intent' => 'pi_webhook_invoice', 'amount_total' => 10000, 'currency' => 'usd',
            'metadata' => ['invoice_id' => $this->invoice->id, 'tenant_id' => $this->invoice->tenant_id],
        ]],
    ]);
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_invoice_test');
    $this->call('POST', route('webhooks.stripe'), [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
    ], $payload)->assertOk();
    expect($this->invoice->fresh()->status)->toBe($paymentStatus === 'paid' ? Invoice::STATUS_PAID : Invoice::STATUS_ISSUED);
})->with(['paid', 'unpaid']);

it('requires CSRF even when the checkout bearer signature is valid', function (): void {
    $url = app(InvoicePaymentLinkService::class)->signedUrl($this->invoice, 'billing.payment.pay');
    app()->instance('env', 'local');
    try {
        $this->post($url)->assertStatus(419);
        Http::assertNothingSent();
    } finally {
        app()->instance('env', 'testing');
    }
});

it('rate limits repeated signed checkout attempts', function (): void {
    Http::fake(['api.paystack.co/transaction/initialize' => Http::response([
        'data' => ['authorization_url' => 'https://checkout.paystack.com/test'],
    ])]);
    $url = app(InvoicePaymentLinkService::class)->signedUrl($this->invoice, 'billing.payment.pay');
    $this->get(app(InvoicePaymentLinkService::class)->url($this->invoice))->assertOk();
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post($url)->assertRedirect();
    }
    $this->post($url)->assertTooManyRequests();
    Http::assertSentCount(5);
});

it('records a second distinct verified payment for reconciliation without rewriting the receipt', function (): void {
    $paid = fulfillLifecycleInvoice($this->invoice, 'first-verified');
    $receipt = $paid->meta['receipt'];
    fulfillLifecycleInvoice($paid, 'second-verified');
    fulfillLifecycleInvoice($paid, 'second-verified');
    expect(Transaction::count())->toBe(2)
        ->and(Transaction::where('provider_transaction_id', 'second-verified')->sole()->status)->toBe('reconciliation_required')
        ->and($paid->fresh()->meta['receipt'])->toEqual($receipt);
    Event::assertDispatchedTimes(InvoicePaymentReceived::class, 1);
    $this->get(app(InvoicePaymentLinkService::class)->url($paid))->assertOk()->assertSee('additional payment was received');
});

it('shows a clear unavailable state without opening checkout when Paystack is not configured', function (): void {
    config(['services.paystack.secret_key' => null]);
    $links = app(InvoicePaymentLinkService::class);
    $this->get($links->url($this->invoice))->assertOk()->assertSee('Online payment is awaiting setup')->assertDontSee('<button type="submit">Pay', false);
    $this->post($links->signedUrl($this->invoice, 'billing.payment.pay'))->assertRedirect()->assertSessionHas('error');
    Http::assertNothingSent();
});
