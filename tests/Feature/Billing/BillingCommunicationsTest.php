<?php

declare(strict_types=1);

use App\Mail\InvoiceBillingNotice;
use App\Models\BillingMessage;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\TenantBillingAgreement;
use App\Services\Billing\BillingCommunicationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    Mail::fake();
    config(['services.paystack.secret_key' => 'test-billing-secret']);
    $this->travelTo(Carbon\Carbon::parse('2026-10-25 08:00:00'));
    $this->tenant = Tenant::factory()->create(['country' => 'Ghana', 'email' => 'owner@example.org', 'meta' => ['billing_email' => 'finance@example.org']]);
    $this->service = app(BillingCommunicationService::class);
    $this->invoice = fn (string $status = 'issued'): Invoice => Invoice::create([
        'tenant_id' => $this->tenant->id, 'number' => 'INV-'.Illuminate\Support\Str::random(8),
        'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'due_at' => '2026-11-01',
        'status' => $status, 'currency' => 'GHS', 'subtotal' => 600, 'tax_total' => 0, 'total' => 600,
    ]);
});

it('records one cycle notice per billing contact and milestone using the upcoming snapshot', function (): void {
    TenantBillingAgreement::factory()->create(['tenant_id' => $this->tenant->id, 'effective_from' => '2026-11-01', 'plan_slug' => 'managed_business', 'editor_seats' => 5]);
    $this->service->runDue();
    $this->service->runDue();
    $message = BillingMessage::query()->sole();
    expect($message->recipient)->toBe('finance@example.org')->and($message->kind)->toBe('cycle_upcoming')
        ->and($message->payload['fixed_amount'])->toEqual(600.0)->and($message->payload['currency'])->toBe('GHS');
    expect(Invoice::count())->toBe(0);
});

it('keeps unconverted and banned organisations out of cycle notices', function (): void {
    $other = Tenant::factory()->create(['status' => 'banned']);
    TenantBillingAgreement::factory()->create(['tenant_id' => $other->id, 'effective_from' => '2026-11-01']);
    $this->service->runDue();
    expect(BillingMessage::count())->toBe(0);
});

it('queues issued invoices once and permits explicit recorded resends', function (): void {
    $invoice = ($this->invoice)();
    $this->service->issued($invoice);
    $this->service->issued($invoice);
    expect(BillingMessage::count())->toBe(1);
    $this->service->issued($invoice, true);
    expect(BillingMessage::count())->toBe(2);
});

it('reminds issued invoices but never drafts paid or void documents', function (): void {
    foreach (['draft', 'paid', 'void', 'issued'] as $status) {
        ($this->invoice)($status);
    }
    $this->service->runDue();
    $this->service->runDue();
    expect(BillingMessage::count())->toBe(1)->and(BillingMessage::sole()->kind)->toBe('invoice_due');
});

it('suppresses a queued reminder when payment occurs before delivery', function (): void {
    $invoice = ($this->invoice)();
    $this->service->runDue();
    $invoice->update(['status' => 'paid']);
    $this->service->deliver(BillingMessage::sole()->id);
    Mail::assertNothingSent();
    expect(BillingMessage::sole()->status)->toBe('cancelled');
});

it('delivers currency-correct messages once and records transport acceptance', function (): void {
    $invoice = ($this->invoice)();
    $this->service->issued($invoice);
    $id = BillingMessage::sole()->id;
    $this->service->deliver($id);
    $this->service->deliver($id);
    Mail::assertSent(InvoiceBillingNotice::class, 1);
    Mail::assertSent(InvoiceBillingNotice::class, fn ($mail) => $mail->hasTo('finance@example.org') && str_contains($mail->render(), 'GHS 600.00'));
    expect(BillingMessage::sole()->status)->toBe('sent')->and(BillingMessage::sole()->sent_at)->not->toBeNull();
});

it('does not leak a queued invoice to an obsolete billing contact', function (): void {
    $invoice = ($this->invoice)();
    $this->service->issued($invoice);
    $this->tenant->update(['meta' => ['billing_email' => 'new-finance@example.org']]);
    $this->service->deliver(BillingMessage::sole()->id);
    Mail::assertNothingSent();
    expect(BillingMessage::sole()->status)->toBe('cancelled');
});

it('records safe failure information and leaves failed delivery retryable', function (): void {
    $invoice = ($this->invoice)();
    $this->service->issued($invoice);
    Mail::shouldReceive('to')->andThrow(new RuntimeException('secret transport password must never be saved'));
    expect(fn () => $this->service->deliver(BillingMessage::sole()->id))->toThrow(RuntimeException::class);
    expect(BillingMessage::sole()->status)->toBe('failed')->and(BillingMessage::sole()->last_error)->not->toContain('password');
});

it('issues drafts atomically without notifying every organisation member', function (): void {
    $invoice = ($this->invoice)('draft');
    $this->tenant->users()->attach(App\Models\User::factory()->create(['email' => 'staff@example.org']));
    expect($this->service->issue($invoice))->toBeTrue()->and($this->service->issue($invoice))->toBeFalse();
    expect($invoice->fresh()->status)->toBe('issued')->and(BillingMessage::sole()->recipient)->toBe('finance@example.org');
});

it('marks late issued invoices overdue and sends each overdue milestone once', function (): void {
    $invoice = ($this->invoice)();
    $this->travelTo(Carbon\Carbon::parse('2026-11-08 08:00:00'));
    $this->service->runDue();
    $this->service->runDue();
    expect($invoice->fresh()->status)->toBe('overdue')->and(BillingMessage::sole()->kind)->toBe('invoice_overdue');
});

it('never requests payment for a draft or void invoice even on explicit resend', function (): void {
    $this->service->issued(($this->invoice)('draft'), true);
    $this->service->issued(($this->invoice)('void'), true);
    expect(BillingMessage::count())->toBe(0);
});

it('records receipts once only after verified paid status', function (): void {
    $invoice = ($this->invoice)();
    $this->service->receipt($invoice);
    expect(BillingMessage::count())->toBe(0);
    $invoice->update(['status' => 'paid']);
    $this->service->receipt($invoice);
    $this->service->receipt($invoice);
    expect(BillingMessage::sole()->kind)->toBe('payment_receipt');
});

it('falls back to the organisation contact and skips invalid billing addresses', function (): void {
    $this->tenant->update(['meta' => []]);
    expect($this->service->contact($this->tenant))->toBe('owner@example.org');
    $this->tenant->update(['email' => null]);
    $this->service->issued(($this->invoice)());
    expect(BillingMessage::count())->toBe(0);
});

it('does not resend transport-accepted messages from the scheduled retry scan', function (): void {
    $this->service->issued(($this->invoice)());
    $message = BillingMessage::sole();
    $message->update(['status' => 'sent', 'sent_at' => now()]);
    Queue::fake();
    $this->service->runDue();
    Queue::assertNotPushed(SendBillingMessage::class, fn ($job) => $job->messageId === $message->id);
});

it('sends the correct first-month setup information without creating an invoice', function (): void {
    TenantBillingAgreement::factory()->create(['tenant_id' => $this->tenant->id, 'effective_from' => '2026-11-01', 'setup_required' => true]);
    $this->service->runDue();
    $message = BillingMessage::sole();
    $mail = new InvoiceBillingNotice($message);
    expect($mail->render())->toContain('GHS 300.00')->toContain('GHS 500.00')->toContain('not an invoice');
    expect(Invoice::count())->toBe(0);
});

it('exposes the reminder command without producing duplicate records on repeat execution', function (): void {
    ($this->invoice)();
    $this->artisan('billing:send-reminders')->assertSuccessful();
    $this->artisan('billing:send-reminders')->assertSuccessful();
    expect(BillingMessage::count())->toBe(1);
});

it('allows website-only and unassigned billing managers to recover billing without paid operational activity', function (bool $assigned): void {
    $member = App\Models\User::factory()->create();
    $tenant = setActiveTenantForTest($member, ['country' => 'Ghana']);
    grantOrganisationAdminAccess($member);
    TenantBillingAgreement::factory()->create(['tenant_id' => $tenant->id, 'effective_from' => now()->startOfMonth()]);
    if ($assigned) {
        app(App\Services\Tenancy\BillingAccessService::class)->assign($tenant, $member, 'website_editor');
    }
    $this->actingAs($member)->get(route('billing.index'))->assertOk()->assertSee('Billing email history');
    expect(App\Models\TenantBillingMonthlyUser::where('tenant_id', $tenant->id)->count())->toBe(0);
})->with([true, false]);

it('enforces provider references globally in the payment ledger', function (): void {
    $data = ['tenant_id' => $this->tenant->id, 'provider' => 'paystack', 'provider_transaction_id' => 'same-verified-payment', 'amount' => 60000, 'currency' => 'GHS', 'status' => 'success', 'type' => 'charge'];
    App\Models\Transaction::create($data);
    $data['tenant_id'] = Tenant::factory()->create()->id;
    expect(fn () => Illuminate\Support\Facades\DB::connection('landlord')->transaction(fn () => App\Models\Transaction::create($data)))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('holds payment-request mail until the provider is configured and resumes safely', function (): void {
    config(['services.paystack.secret_key' => null]);
    $this->service->issued(($this->invoice)());
    $id = BillingMessage::sole()->id;
    $this->service->deliver($id);
    expect(BillingMessage::sole()->status)->toBe('blocked')->and(BillingMessage::sole()->attempts)->toBe(0);
    Mail::assertNothingSent();
    config(['services.paystack.secret_key' => 'test-configured']);
    $this->service->deliver($id);
    Mail::assertSent(InvoiceBillingNotice::class, 1);
});

it('cannot reopen a concurrently paid invoice when marking stale rows overdue', function (): void {
    $invoice = ($this->invoice)();
    $this->travelTo(Carbon\Carbon::parse('2026-11-08 08:00:00'));
    $changed = false;
    Invoice::retrieved(function (Invoice $loaded) use ($invoice, &$changed): void {
        if ($loaded->id === $invoice->id && ! $changed) {
            $changed = true;
            Invoice::query()->whereKey($invoice->id)->update(['status' => 'paid']);
        }
    });
    $this->service->runDue();
    expect($invoice->fresh()->status)->toBe('paid')->and(BillingMessage::count())->toBe(0);
});

it('coalesces blocked payment reminders to the latest message before resuming', function (): void {
    $invoice = ($this->invoice)();
    config(['services.paystack.secret_key' => null]);
    $this->service->issued($invoice);
    $this->travelTo(Carbon\Carbon::parse('2026-10-29 08:00:00'));
    $this->service->runDue();
    $this->travelTo(Carbon\Carbon::parse('2026-11-08 08:00:00'));
    $this->service->runDue();
    config(['services.paystack.secret_key' => 'ready']);
    foreach (BillingMessage::query()->oldest()->get() as $message) {
        $this->service->deliver($message->id);
    }
    Mail::assertSent(InvoiceBillingNotice::class, 1);
    expect(BillingMessage::where('status', 'sent')->count())->toBe(1)
        ->and(BillingMessage::where('status', 'cancelled')->count())->toBe(2);
});
