<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\User;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Models\FinInvoice;
use App\Modules\Finance\Models\FinPayment;
use App\Modules\Finance\Services\FinanceReports;
use App\Modules\Finance\Services\InvoiceService;
use App\Modules\Finance\Services\OnlinePaymentService;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Finance\Services\VatReport;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: User, 1: Tenant, 2: FinContact}
 */
function createCurrencyContext(): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = config('modules.finance.permissions');
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web'], ['category' => 'finance', 'uuid' => (string) Str::uuid()]);
    }
    $user->givePermissionTo($permissions);

    app(TenantDatabaseManager::class)->configure($tenant);
    TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'module-finance', 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
    app(ModuleManager::class)->enableForTenant('finance', $tenant);
    Artisan::call('migrate', ['--database' => 'tenant', '--path' => app_path('Modules/Finance/Database/Migrations'), '--realpath' => true, '--force' => true]);

    return [$user, $tenant, FinContact::query()->create(['tenant_id' => $tenant->id, 'name' => 'Global Funder', 'email' => 'funder@example.test', 'is_customer' => true, 'is_active' => true])];
}

function usdInvoice(Tenant $tenant, FinContact $contact, int $price, float $rate, string $type = FinInvoice::TYPE_INVOICE): FinInvoice
{
    $service = app(InvoiceService::class);
    $draft = $service->saveDraft($tenant->id, $contact, ['type' => $type, 'issue_date' => today()->toDateString(), 'currency' => 'USD', 'exchange_rate' => $rate], [['description' => 'Programme support', 'quantity' => 1, 'unit_price' => $price]]);

    return $service->send($draft);
}

function ledgerBase(Tenant $tenant, string $code): int
{
    $account = FinAccount::query()->where('tenant_id', $tenant->id)->where('code', $code)->first();

    return $account === null ? 0 : (int) DB::connection('tenant')->table('fin_journal_lines')->where('account_id', $account->id)->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as net')->value('net');
}

describe('Foreign-currency invoices', function (): void {
    test('a USD invoice is kept in dollars and booked in the base currency at its rate', function (): void {
        [, $tenant, $contact] = createCurrencyContext();
        $invoice = usdInvoice($tenant, $contact, 100_000, 15.5);

        expect($invoice->currency)->toBe('USD')->and($invoice->total)->toBe(100_000)->and((float) $invoice->exchange_rate)->toBe(15.5)
            ->and($invoice->baseTotal())->toBe(1_550_000)->and(ledgerBase($tenant, '1100'))->toBe(1_550_000)->and(ledgerBase($tenant, '4000'))->toBe(-1_550_000);
    });

    test('rates and currencies are validated and foreign documents carry no tax', function (): void {
        [, $tenant, $contact] = createCurrencyContext();
        $service = app(InvoiceService::class);
        $line = [['description' => 'X', 'quantity' => 1, 'unit_price' => 1000]];

        expect(fn () => $service->saveDraft($tenant->id, $contact, ['issue_date' => today()->toDateString(), 'currency' => 'USD'], $line))->toThrow(DomainException::class, 'exchange rate')
            ->and(fn () => $service->saveDraft($tenant->id, $contact, ['issue_date' => today()->toDateString(), 'currency' => 'USD', 'exchange_rate' => 0], $line))->toThrow(DomainException::class, 'exchange rate')
            ->and(fn () => $service->saveDraft($tenant->id, $contact, ['issue_date' => today()->toDateString(), 'currency' => 'JPY', 'exchange_rate' => 0.1], $line))->toThrow(DomainException::class, 'not supported');

        $base = $service->saveDraft($tenant->id, $contact, ['issue_date' => today()->toDateString(), 'currency' => 'GHS', 'exchange_rate' => 99], $line);
        expect($base->currency)->toBe('GHS')->and((float) $base->exchange_rate)->toBe(1.0);
    });

    test('rounding differences between lines and total never unbalance the books', function (): void {
        [, $tenant, $contact] = createCurrencyContext();
        $service = app(InvoiceService::class);
        $draft = $service->saveDraft($tenant->id, $contact, ['issue_date' => today()->toDateString(), 'currency' => 'USD', 'exchange_rate' => 15.333333], [
            ['description' => 'A', 'quantity' => 1, 'unit_price' => 3333],
            ['description' => 'B', 'quantity' => 1, 'unit_price' => 3333, 'account_id' => FinAccount::query()->where('tenant_id', $tenant->id)->where('code', '4100')->value('id')],
            ['description' => 'C', 'quantity' => 1, 'unit_price' => 3333],
        ]);
        $sent = $service->send($draft);

        expect($sent->status)->toBe(FinInvoice::STATUS_SENT)->and(ledgerBase($tenant, '1100'))->toBe($sent->baseTotal())
            ->and(ledgerBase($tenant, '4000') + ledgerBase($tenant, '4100'))->toBe(-$sent->baseTotal());
    });

    test('a credit note copies the currency and rate and reverses the booking', function (): void {
        [, $tenant, $contact] = createCurrencyContext();
        $invoice = usdInvoice($tenant, $contact, 50_000, 16.0);
        $credit = app(InvoiceService::class)->createCreditNote($invoice);
        app(InvoiceService::class)->send($credit);

        expect($credit->currency)->toBe('USD')->and((float) $credit->exchange_rate)->toBe(16.0)->and(ledgerBase($tenant, '1100'))->toBe(0);
    });

    test('foreign invoices are not offered for online card payment', function (): void {
        [, $tenant, $contact] = createCurrencyContext();
        $usd = usdInvoice($tenant, $contact, 10_000, 15.0);

        expect(app(OnlinePaymentService::class)->canCollect($usd))->toBeFalse();
    });
});

describe('Foreign-currency payments', function (): void {
    test('a payment at a higher rate books a gain, at a lower rate a loss, and clears the invoice', function (): void {
        [, $tenant, $contact] = createCurrencyContext();
        $payments = app(PaymentService::class);

        $gainInvoice = usdInvoice($tenant, $contact, 100_000, 15.0);
        $gain = $payments->record($tenant->id, $contact, 100_000, CarbonImmutable::today(), [$gainInvoice->id => 100_000], ['exchange_rate' => 16.0, 'method' => 'bank_transfer']);
        expect($gain->currency)->toBe('USD')->and($gain->base_amount)->toBe(1_600_000)->and($gainInvoice->fresh()->status)->toBe(FinInvoice::STATUS_PAID)
            ->and(ledgerBase($tenant, '1000'))->toBe(1_600_000)->and(ledgerBase($tenant, '1100'))->toBe(0)->and(ledgerBase($tenant, '4900'))->toBe(-100_000);

        $lossInvoice = usdInvoice($tenant, $contact, 100_000, 16.0);
        $payments->record($tenant->id, $contact, 100_000, CarbonImmutable::today(), [$lossInvoice->id => 100_000], ['exchange_rate' => 15.5, 'method' => 'bank_transfer']);
        expect(ledgerBase($tenant, '1100'))->toBe(0)->and(ledgerBase($tenant, '4900'))->toBe(-100_000 + 50_000)->and(ledgerBase($tenant, '1000'))->toBe(1_600_000 + 1_550_000);

        $sum = DB::connection('tenant')->table('fin_journal_lines')->selectRaw('SUM(debit) as d, SUM(credit) as c')->first();
        expect((int) $sum->d)->toBe((int) $sum->c);
    });

    test('a partial payment clears its share at the invoice rate, and voiding it restores the books', function (): void {
        [, $tenant, $contact] = createCurrencyContext();
        $payments = app(PaymentService::class);
        $invoice = usdInvoice($tenant, $contact, 100_000, 15.0);

        $payment = $payments->record($tenant->id, $contact, 40_000, CarbonImmutable::today(), [$invoice->id => 40_000], ['exchange_rate' => 15.0]);
        expect($invoice->fresh()->status)->toBe(FinInvoice::STATUS_PARTLY_PAID)->and($invoice->fresh()->balanceDue())->toBe(60_000)
            ->and(ledgerBase($tenant, '1100'))->toBe(900_000)->and(ledgerBase($tenant, '4900'))->toBe(0);

        $payments->void($payment);
        expect($invoice->fresh()->balanceDue())->toBe(100_000)->and(ledgerBase($tenant, '1100'))->toBe(1_500_000);
    });

    test('mixed currencies, missing rates, unallocated credit and wrong currencies are refused', function (): void {
        [, $tenant, $contact] = createCurrencyContext();
        $payments = app(PaymentService::class);
        $usd = usdInvoice($tenant, $contact, 100_000, 15.0);
        $ghs = app(InvoiceService::class)->send(app(InvoiceService::class)->saveDraft($tenant->id, $contact, ['issue_date' => today()->toDateString()], [['description' => 'Local', 'quantity' => 1, 'unit_price' => 5000]]));
        $today = CarbonImmutable::today();

        expect(fn () => $payments->record($tenant->id, $contact, 105_000, $today, [$usd->id => 100_000, $ghs->id => 5000], ['exchange_rate' => 15.0]))->toThrow(DomainException::class, 'single currency')
            ->and(fn () => $payments->record($tenant->id, $contact, 100_000, $today, [$usd->id => 100_000], []))->toThrow(DomainException::class, 'exchange rate')
            ->and(fn () => $payments->record($tenant->id, $contact, 120_000, $today, [$usd->id => 100_000], ['exchange_rate' => 15.0]))->toThrow(DomainException::class, 'allocated in full')
            ->and(fn () => $payments->record($tenant->id, $contact, 100_000, $today, [$usd->id => 100_000], ['exchange_rate' => 15.0, 'currency' => 'EUR']))->toThrow(DomainException::class, 'must match')
            ->and(fn () => $payments->record($tenant->id, $contact, 5000, $today, [$ghs->id => 5000], ['currency' => 'USD']))->toThrow(DomainException::class, 'must be applied');
        expect(FinPayment::query()->count())->toBe(0);
    });
});

describe('Reports with foreign currency', function (): void {
    test('aged receivables total in the base currency and the VAT return counts foreign sales at their booked value', function (): void {
        [, $tenant, $contact] = createCurrencyContext();
        usdInvoice($tenant, $contact, 100_000, 15.0);
        $aged = app(FinanceReports::class)->agedReceivables($tenant->id);

        expect($aged['totals']['total'])->toBe(1_500_000)->and($aged['rows']->first()['balance'])->toBe(1_500_000);

        $vat = app(VatReport::class)->summary($tenant->id, CarbonImmutable::today()->startOfMonth(), CarbonImmutable::today());
        expect($vat['sales']['uncoded'])->toBe(1_500_000);
    });

    test('the invoice screens create a foreign document, show its booked value, and payments take a rate', function (): void {
        [$user, $tenant, $contact] = createCurrencyContext();
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->get(route('finance.invoices.create'))->assertOk()->assertSee('US dollar');
        $this->post(route('finance.invoices.store'), ['type' => 'invoice', 'contact_id' => $contact->id, 'issue_date' => today()->toDateString(), 'currency' => 'USD', 'exchange_rate' => '15.25', 'lines' => [['description' => 'Grant management fee', 'quantity' => 1, 'unit_price' => '1000']]])->assertRedirect();
        $invoice = FinInvoice::query()->firstOrFail();
        expect($invoice->currency)->toBe('USD')->and($invoice->total)->toBe(100_000);

        $this->post(route('finance.invoices.send', $invoice->uuid))->assertSessionHas('success');
        $this->get(route('finance.invoices.show', $invoice->uuid))->assertOk()->assertSee('Booked at 15.25');

        $this->post(route('finance.payments.store'), ['contact_id' => $contact->id, 'date' => today()->toDateString(), 'amount' => '1000', 'method' => 'bank_transfer', 'allocations' => [$invoice->id => '1000']])->assertSessionHas('error');
        $this->post(route('finance.payments.store'), ['contact_id' => $contact->id, 'date' => today()->toDateString(), 'amount' => '1000', 'method' => 'bank_transfer', 'exchange_rate' => '15.5', 'allocations' => [$invoice->id => '1000']])->assertSessionHas('success');
        $this->get(route('finance.payments.index'))->assertOk()->assertSee('USD');
        expect(ledgerBase($tenant, '4900'))->toBe(-25_000);
    });
});
