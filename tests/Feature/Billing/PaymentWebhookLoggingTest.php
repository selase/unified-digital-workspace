<?php

declare(strict_types=1);

use App\Models\BillingMessage;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\Transaction;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Monolog\Handler\TestHandler;

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->productionLogConfig = config('logging.channels.payment_webhooks');
    config(['services.paystack.secret_key' => 'audit-test-secret',
        'logging.channels.payment_webhooks' => ['driver' => 'monolog', 'handler' => TestHandler::class]]);
    Log::forgetChannel('payment_webhooks');
    $this->auditLogs = Log::channel('payment_webhooks')->getLogger()->getHandlers()[0];
    $this->auditInvoice = Invoice::query()->create([
        'tenant_id' => Tenant::factory()->create(['email' => 'billing@example.test'])->id,
        'number' => 'AUDIT-'.Str::uuid(), 'period_start' => today(), 'period_end' => today(),
        'due_at' => today()->addDays(7), 'status' => Invoice::STATUS_ISSUED,
        'currency' => 'GHS', 'subtotal' => 2, 'tax_total' => 0, 'total' => 2,
    ]);
    $this->auditPayment = ['id' => 789123, 'status' => 'success', 'amount' => 200, 'currency' => 'GHS',
        'metadata' => ['application' => 'udworkspace', 'invoice_id' => $this->auditInvoice->id, 'tenant_id' => $this->auditInvoice->tenant_id]];
    $this->auditPayload = json_encode(['event' => 'charge.success', 'data' => [
        'reference' => 'audit-ref', 'customer' => ['email' => 'never-log@example.test'],
        'authorization' => ['authorization_code' => 'never-log-authorization'],
    ]], JSON_THROW_ON_ERROR);
});

function sendAuditWebhook(string $payload, bool $signed = true): Illuminate\Testing\TestResponse
{
    return test()->call('POST', route('webhooks.paystack'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => $signed ? hash_hmac('sha512', $payload, 'audit-test-secret') : 'never-log-signature',
    ], $payload);
}

it('logs invalid signatures with correlation but without private request data', function (): void {
    $response = sendAuditWebhook($this->auditPayload, false)->assertBadRequest();
    $records = $this->auditLogs->getRecords();
    expect($records)->toHaveCount(2)
        ->and($records[0]['message'])->toBe('payment_webhook.received')
        ->and($records[0]['context']['payload_sha256'])->toBe(hash('sha256', $this->auditPayload))
        ->and($records[1]['context']['outcome'])->toBe('invalid_signature')
        ->and($records[1]['context']['signature_valid'])->toBeFalse()
        ->and($records[1]['context']['http_status'])->toBe(400)
        ->and($records[1]['context']['attempt_id'])->toBe($response->headers->get('X-Webhook-Attempt'));
    foreach (['never-log@example.test', 'never-log-authorization', 'never-log-signature', 'audit-test-secret', 'audit-ref'] as $private) {
        expect(json_encode($records))->not->toContain($private);
    }
    Http::assertNothingSent();
});

it('logs provider failures without exception messages', function (): void {
    Http::fake(fn () => throw new ConnectionException('never-log-provider-secret'));
    sendAuditWebhook($this->auditPayload)->assertServerError();
    $record = $this->auditLogs->getRecords()[1];
    expect($record['context']['http_status'])->toBe(500)
        ->and($record['context']['outcome'])->toBe('processing_error')
        ->and($record['context']['exception_class'])->toBe(ConnectionException::class)
        ->and(json_encode($this->auditLogs->getRecords()))->not->toContain('never-log-provider-secret');
});

it('logs payment decisions without settling rejected or unrelated payments', function (string $kind, string $outcome, int $status): void {
    $data = $this->auditPayment;
    $payload = $this->auditPayload;
    match ($kind) {
        'foreign' => $data['metadata']['application'] = 'foreign-app',
        'unknown' => $data['metadata']['invoice_id'] = (string) Str::uuid(),
        'mismatch' => $data['amount'] = 100,
        'unconfirmed' => $data['status'] = 'pending',
        'event' => $payload = json_encode(['event' => 'refund.processed']),
        'reference' => $payload = json_encode(['event' => 'charge.success', 'data' => ['reference' => ['invalid']]]),
    };
    Http::fake(['api.paystack.co/transaction/verify/audit-ref' => Http::response(['data' => $data])]);
    sendAuditWebhook($payload)->assertStatus($status);
    expect($this->auditLogs->getRecords()[1]['context']['outcome'])->toBe($outcome)
        ->and($this->auditInvoice->fresh()->status)->toBe(Invoice::STATUS_ISSUED);
    if (in_array($kind, ['event', 'reference'], true)) {
        Http::assertNothingSent();
    }
})->with([
    ['foreign', 'ignored_application', 200], ['unknown', 'ignored_invoice', 200],
    ['mismatch', 'payment_mismatch', 422], ['unconfirmed', 'payment_unconfirmed', 422],
    ['event', 'ignored_event', 200], ['reference', 'invalid_reference', 400],
]);

it('logs verified replays with one transaction and one receipt email', function (): void {
    Http::fake(['api.paystack.co/transaction/verify/audit-ref' => Http::response(['data' => $this->auditPayment])]);
    $first = sendAuditWebhook($this->auditPayload)->assertOk();
    $receipt = $this->auditInvoice->fresh()->meta['receipt'];
    $second = sendAuditWebhook($this->auditPayload)->assertOk();
    $records = $this->auditLogs->getRecords();
    expect($records)->toHaveCount(4)
        ->and($records[1]['context']['outcome'])->toBe('processed')
        ->and($records[3]['context']['outcome'])->toBe('duplicate')
        ->and($records[3]['context']['invoice_id'])->toBe($this->auditInvoice->id)
        ->and($records[3]['context']['provider_transaction_id'])->toBe('789123')
        ->and($records[3]['context']['reference'])->toBe('audit-ref')
        ->and($first->headers->get('X-Webhook-Attempt'))->not->toBe($second->headers->get('X-Webhook-Attempt'))
        ->and($this->auditInvoice->fresh()->meta['receipt'])->toBe($receipt)
        ->and(Transaction::query()->where('provider', 'paystack')->where('provider_transaction_id', '789123')->count())->toBe(1)
        ->and(BillingMessage::query()->where('invoice_id', $this->auditInvoice->id)->where('kind', 'payment_receipt')->count())->toBe(1);
});

it('writes private structured rotating log files with thirty day retention', function (): void {
    $base = storage_path('logs/payment-webhooks-test-'.Str::uuid().'.log');
    config(['logging.channels.payment_webhooks' => [...$this->productionLogConfig, 'path' => $base]]);
    Log::forgetChannel('payment_webhooks');
    try {
        Log::channel('payment_webhooks')->info('safe_probe', ['attempt_id' => 'test-attempt']);
        $files = glob(str_replace('.log', '-*.log', $base));
        expect($files)->toHaveCount(1)
            ->and($this->productionLogConfig['days'])->toBe(30)
            ->and($this->productionLogConfig['locking'])->toBeTrue()
            ->and(fileperms($files[0]) & 0777)->toBe(0600)
            ->and(json_decode(file_get_contents($files[0]), true, flags: JSON_THROW_ON_ERROR)['context']['attempt_id'])->toBe('test-attempt');
    } finally {
        Log::forgetChannel('payment_webhooks');
        foreach (glob(str_replace('.log', '-*.log', $base)) as $file) {
            unlink($file);
        }
    }
});

it('logs an additional payment for review without replacing the receipt or emailing twice', function (): void {
    Http::fake(['api.paystack.co/transaction/verify/audit-ref' => Http::response(['data' => $this->auditPayment])]);
    sendAuditWebhook($this->auditPayload)->assertOk();
    $receipt = $this->auditInvoice->fresh()->meta['receipt'];
    $additional = $this->auditPayment;
    $additional['id'] = 789124;
    Http::fake(['api.paystack.co/transaction/verify/audit-second' => Http::response(['data' => $additional])]);
    sendAuditWebhook(str_replace('audit-ref', 'audit-second', $this->auditPayload))->assertOk();
    expect($this->auditLogs->getRecords()[3]['context']['outcome'])->toBe('reconciliation_required')
        ->and(Transaction::query()->where('provider_transaction_id', '789124')->value('status'))->toBe('reconciliation_required')
        ->and($this->auditInvoice->fresh()->meta['receipt'])->toBe($receipt)
        ->and(BillingMessage::query()->where('invoice_id', $this->auditInvoice->id)->where('kind', 'payment_receipt')->count())->toBe(1);
});
