<?php

declare(strict_types=1);

use App\Enum\TenantStatusEnum;
use App\Models\AlertDelivery;
use App\Models\AlertRule;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AlertNotification;
use App\Services\Alerts\AlertDispatcher;
use App\Services\Alerts\AlertEvent;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    refreshTenantDatabases();
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    $this->artisan('db:seed', ['--class' => 'PermissionsSeeder']);

    $this->tenant = Tenant::create(['name' => 'Hospital A', 'slug' => 'hospital-a', 'status' => TenantStatusEnum::ACTIVE]);
    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'phone_no' => '+233244123456']);
    $this->admin->tenants()->attach($this->tenant->id);
    setPermissionsTeamId($this->tenant->id);
    $this->admin->assignRole('Org Superadmin');
    $this->dispatcher = app(AlertDispatcher::class);
});

function makeRule(Tenant $tenant, array $overrides = []): AlertRule
{
    return AlertRule::create([
        'tenant_id' => $tenant->id, 'event' => AlertEvent::INVOICE_OVERDUE, 'name' => 'Overdue', 'channels' => ['mail'],
        'recipients' => ['emails' => [], 'phones' => []], ...$overrides,
    ]);
}

it('notifies the organisation admins once per occurrence on the chosen channels', function (): void {
    Notification::fake();
    makeRule($this->tenant, ['channels' => ['mail', 'database']]);

    $sent = $this->dispatcher->dispatch($this->tenant, AlertEvent::INVOICE_OVERDUE, 'Invoice overdue', 'INV-1 is overdue', 'invoice-1');
    $again = $this->dispatcher->dispatch($this->tenant, AlertEvent::INVOICE_OVERDUE, 'Invoice overdue', 'INV-1 is overdue', 'invoice-1');

    expect($sent)->toBe(1)->and($again)->toBe(0)
        ->and(AlertDelivery::count())->toBe(1);
    Notification::assertSentToTimes($this->admin, AlertNotification::class, 1);
    Notification::assertSentTo($this->admin, AlertNotification::class, fn (AlertNotification $n): bool => $n->channels === ['mail', 'database'] && $n->event === AlertEvent::INVOICE_OVERDUE);
});

it('alerts again for a new occurrence', function (): void {
    Notification::fake();
    makeRule($this->tenant);

    $this->dispatcher->dispatch($this->tenant, AlertEvent::INVOICE_OVERDUE, 'Overdue', 'INV-1', 'invoice-1');
    $this->dispatcher->dispatch($this->tenant, AlertEvent::INVOICE_OVERDUE, 'Overdue', 'INV-2', 'invoice-2');

    Notification::assertSentToTimes($this->admin, AlertNotification::class, 2);
});

it('drops channels the organisation has switched off, and does nothing when none are left', function (): void {
    Notification::fake();
    makeRule($this->tenant, ['channels' => ['mail', 'sms']]);

    $this->dispatcher->dispatch($this->tenant, AlertEvent::INVOICE_OVERDUE, 'Overdue', 'x', 'k1');
    Notification::assertSentTo($this->admin, AlertNotification::class, fn (AlertNotification $n): bool => $n->channels === ['mail']);

    $this->tenant->update(['meta' => ['alert_channels' => ['mail' => false, 'sms' => false, 'database' => true]]]);
    expect($this->dispatcher->dispatch($this->tenant->fresh(), AlertEvent::INVOICE_OVERDUE, 'Overdue', 'x', 'k2'))->toBe(0);
});

it('sends by SMS when the organisation has turned SMS on', function (): void {
    Notification::fake();
    $this->tenant->update(['meta' => ['alert_channels' => ['mail' => true, 'sms' => true, 'database' => true]]]);
    makeRule($this->tenant, ['channels' => ['sms']]);

    $this->dispatcher->dispatch($this->tenant->fresh(), AlertEvent::INVOICE_OVERDUE, 'Overdue', 'x', 'k1');

    Notification::assertSentTo($this->admin, AlertNotification::class, fn (AlertNotification $n): bool => $n->channels === ['sms']);
});

it('also alerts extra emails and phones, without in-app notices for them', function (): void {
    Notification::fake();
    $this->tenant->update(['meta' => ['alert_channels' => ['mail' => true, 'sms' => true, 'database' => true]]]);
    makeRule($this->tenant, ['channels' => ['mail', 'sms', 'database'], 'recipients' => ['emails' => ['finance@hospital-a.test'], 'phones' => ['+233200000000']]]);

    expect($this->dispatcher->dispatch($this->tenant->fresh(), AlertEvent::INVOICE_OVERDUE, 'Overdue', 'x', 'k1'))->toBe(3);
    Notification::assertSentOnDemand(AlertNotification::class, fn (AlertNotification $n, array $channels, object $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'finance@hospital-a.test' && $n->channels === ['mail', 'sms']);
    Notification::assertSentOnDemand(AlertNotification::class, fn (AlertNotification $n, array $channels, object $notifiable): bool => ($notifiable->routes['sms'] ?? null) === '+233200000000');
});

it('ignores switched-off rules, other events and other organisations', function (): void {
    Notification::fake();
    makeRule($this->tenant, ['is_active' => false]);
    makeRule($this->tenant, ['event' => AlertEvent::USAGE_LIMIT]);
    $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => TenantStatusEnum::ACTIVE]);
    makeRule($other);

    expect($this->dispatcher->dispatch($this->tenant, AlertEvent::INVOICE_OVERDUE, 'Overdue', 'x', 'k1'))->toBe(0);
    Notification::assertNothingSent();
});

it('lets an administrator manage channels and rules from the settings page', function (): void {
    $slug = $this->tenant->slug;
    $this->actingAs($this->admin)->get(route('tenant.settings.alerts.index', ['subdomain' => $slug]))->assertOk()->assertSee('Alert channels');

    $this->actingAs($this->admin)->post(route('tenant.settings.alerts.channels', ['subdomain' => $slug]), ['mail' => 1, 'sms' => 1])->assertRedirect();
    expect($this->tenant->fresh()->meta['alert_channels'])->toEqual(['mail' => true, 'database' => false, 'sms' => true]);

    $this->post(route('tenant.settings.alerts.store', ['subdomain' => $slug]), [
        'event' => AlertEvent::FORM_SUBMISSION, 'name' => 'Forms', 'channels' => ['mail', 'sms'], 'emails' => 'a@x.test, b@x.test', 'phones' => '0244123456',
    ])->assertRedirect();

    $rule = AlertRule::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
    expect($rule->recipients['emails'])->toBe(['a@x.test', 'b@x.test'])->and($rule->channels)->toBe(['mail', 'sms']);

    $this->patch(route('tenant.settings.alerts.toggle', ['subdomain' => $slug, 'rule' => $rule->id]))->assertRedirect();
    expect($rule->fresh()->is_active)->toBeFalse();

    $this->delete(route('tenant.settings.alerts.destroy', ['subdomain' => $slug, 'rule' => $rule->id]))->assertRedirect();
    expect(AlertRule::count())->toBe(0);
});

it('rejects bad rules and keeps one organisation out of another’s rules', function (): void {
    $slug = $this->tenant->slug;
    $this->actingAs($this->admin)->post(route('tenant.settings.alerts.store', ['subdomain' => $slug]), ['event' => 'nonsense', 'name' => 'x', 'channels' => []])
        ->assertSessionHasErrors(['event', 'channels']);

    $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => TenantStatusEnum::ACTIVE]);
    $theirs = makeRule($other);
    $this->delete(route('tenant.settings.alerts.destroy', ['subdomain' => $slug, 'rule' => $theirs->id]))->assertNotFound();
    expect(AlertRule::find($theirs->id))->not->toBeNull();
});

it('keeps people without settings permission out', function (): void {
    $editor = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $editor->tenants()->attach($this->tenant->id);
    $this->actingAs($editor)->get(route('tenant.settings.alerts.index', ['subdomain' => $this->tenant->slug]))->assertForbidden();
});

it('raises the usage-limit alert through a rule, and keeps the built-in notice when there is no rule', function (): void {
    Notification::fake();
    App\Models\UsageRollup::create(['tenant_id' => $this->tenant->id, 'metric' => App\Enum\UsageMetric::REQUEST_COUNT, 'period' => 'day', 'period_start' => now()->startOfDay(), 'value' => 800, 'dimensions_hash' => 'test']);
    $limit = App\Models\UsageLimit::create(['tenant_id' => $this->tenant->id, 'metric' => App\Enum\UsageMetric::REQUEST_COUNT, 'limit_value' => 1000, 'period' => 'month', 'alert_threshold' => 70]);

    app(App\Services\Tenancy\UsageLimitService::class)->evaluateAlerts($this->tenant);
    Notification::assertSentTo($this->admin, App\Notifications\UsageLimitAlertNotification::class);
    Notification::assertNotSentTo($this->admin, AlertNotification::class);

    $limit->update(['last_alert_at' => null]);
    makeRule($this->tenant, ['event' => AlertEvent::USAGE_LIMIT]);
    app(App\Services\Tenancy\UsageLimitService::class)->evaluateAlerts($this->tenant);
    Notification::assertSentToTimes($this->admin, AlertNotification::class, 1);
    Notification::assertSentToTimes($this->admin, App\Notifications\UsageLimitAlertNotification::class, 1);
});

it('raises the overdue-invoice alert once when an issued invoice passes its due date', function (): void {
    Notification::fake();
    makeRule($this->tenant);
    $invoice = App\Models\Invoice::create([
        'tenant_id' => $this->tenant->id, 'number' => 'INV-A-1', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'due_at' => now()->subDay()->toDateString(),
        'status' => App\Models\Invoice::STATUS_ISSUED, 'currency' => 'GHS', 'subtotal' => 100, 'tax_total' => 0, 'total' => 100,
    ]);

    app(App\Services\Billing\BillingCommunicationService::class)->runDue();
    app(App\Services\Billing\BillingCommunicationService::class)->runDue();

    expect($invoice->fresh()->status)->toBe(App\Models\Invoice::STATUS_OVERDUE);
    Notification::assertSentToTimes($this->admin, AlertNotification::class, 1);
});

it('raises the low-credit alert once when a prepaid balance runs down', function (): void {
    Notification::fake();
    makeRule($this->tenant, ['event' => AlertEvent::CREDITS_LOW]);
    App\Modules\Store\Models\TenantCreditWallet::create(['uuid' => (string) Illuminate\Support\Str::uuid(), 'tenant_id' => $this->tenant->id, 'credit_type' => 'sms', 'balance' => 12, 'total_purchased' => 100, 'total_consumed' => 88]);
    $credits = app(App\Modules\Store\Services\PlatformCreditService::class);

    $credits->consumeCredit($this->tenant->id, 'sms');
    Notification::assertNothingSent();

    $credits->consumeCredit($this->tenant->id, 'sms');
    $credits->consumeCredit($this->tenant->id, 'sms');
    Notification::assertSentToTimes($this->admin, AlertNotification::class, 1);
});
