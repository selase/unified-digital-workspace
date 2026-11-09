<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantPaymentGateway;
use App\Models\User;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinBill;
use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Models\FinExpense;
use App\Modules\Finance\Models\FinFund;
use App\Modules\Finance\Models\FinInvoice;
use App\Modules\Finance\Models\FinJournal;
use App\Modules\Finance\Models\FinPayment;
use App\Modules\Finance\Models\FinRecurringInvoice;
use App\Modules\Finance\Models\FinTax;
use App\Modules\Finance\Services\BillService;
use App\Modules\Finance\Services\ChartOfAccounts;
use App\Modules\Finance\Services\ExpenseService;
use App\Modules\Finance\Services\FinanceReports;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Services\InvoiceService;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Finance\Services\OnlinePaymentService;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Finance\Services\RecurringInvoiceService;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: User, 1: Tenant}
 */
function createFinanceContext(bool $withPermissions = true): array
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
    if ($withPermissions) {
        $user->givePermissionTo($permissions);
    }

    app(TenantDatabaseManager::class)->configure($tenant);
    App\Models\TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'module-finance', 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
    app(ModuleManager::class)->enableForTenant('finance', $tenant);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/Finance/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

    return [$user, $tenant];
}

function financeCustomer(Tenant $tenant, string $name = 'Acme Ltd', ?string $email = 'pay@acme.test'): FinContact
{
    return FinContact::query()->create(['tenant_id' => $tenant->id, 'name' => $name, 'email' => $email]);
}

function issueInvoice(Tenant $tenant, FinContact $customer, int $unitPrice = 100_000, float $quantity = 1, array $lineExtra = []): FinInvoice
{
    $service = app(InvoiceService::class);
    $draft = $service->saveDraft(
        $tenant->id,
        $customer,
        ['issue_date' => today()->toDateString()],
        [['description' => 'Hosting', 'quantity' => $quantity, 'unit_price' => $unitPrice, ...$lineExtra]],
    );

    return $service->send($draft);
}

function accountBalance(Tenant $tenant, string $systemKey): int
{
    $account = app(ChartOfAccounts::class)->bySystemKey($tenant->id, $systemKey);
    $row = DB::connection('tenant')->table('fin_journal_lines')->where('account_id', $account->id)
        ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')->first();

    return (int) $row->d - (int) $row->c;
}

describe('Finance ledger', function (): void {
    test('rejects journals that do not balance, are too short or use bad amounts', function (): void {
        [, $tenant] = createFinanceContext();
        $ledger = app(LedgerService::class);
        $bank = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::BANK);
        $income = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::DEFAULT_INCOME);

        expect(fn () => $ledger->post($tenant->id, today(), [
            ['account_id' => $bank->id, 'debit' => 1000],
            ['account_id' => $income->id, 'credit' => 900],
        ]))->toThrow(DomainException::class, 'does not balance');

        expect(fn () => $ledger->post($tenant->id, today(), [['account_id' => $bank->id, 'debit' => 1000]]))
            ->toThrow(DomainException::class, 'at least two lines');

        expect(fn () => $ledger->post($tenant->id, today(), [
            ['account_id' => $bank->id, 'debit' => 1000, 'credit' => 1000],
            ['account_id' => $income->id, 'credit' => 0],
        ]))->toThrow(DomainException::class);

        expect(FinJournal::query()->count())->toBe(0);
    });

    test('posts a balanced journal with a gap-free number and tags the fund', function (): void {
        [, $tenant] = createFinanceContext();
        $ledger = app(LedgerService::class);
        $bank = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::BANK);
        $income = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::DEFAULT_INCOME);
        $fund = FinFund::query()->create(['tenant_id' => $tenant->id, 'code' => 'GR1', 'name' => 'Grant One', 'is_restricted' => true]);

        $first = $ledger->post($tenant->id, today(), [
            ['account_id' => $bank->id, 'debit' => 5000, 'fund_id' => $fund->id],
            ['account_id' => $income->id, 'credit' => 5000, 'fund_id' => $fund->id],
        ], 'Grant received');
        $second = $ledger->post($tenant->id, today(), [
            ['account_id' => $bank->id, 'debit' => 100],
            ['account_id' => $income->id, 'credit' => 100],
        ]);

        expect($first->number)->toBe('JNL-00001')
            ->and($second->number)->toBe('JNL-00002')
            ->and($first->lines()->where('fund_id', $fund->id)->count())->toBe(2);
    });

    test('reverses a journal once and never edits the original', function (): void {
        [, $tenant] = createFinanceContext();
        $ledger = app(LedgerService::class);
        $bank = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::BANK);
        $income = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::DEFAULT_INCOME);

        $journal = $ledger->post($tenant->id, today(), [
            ['account_id' => $bank->id, 'debit' => 700],
            ['account_id' => $income->id, 'credit' => 700],
        ]);
        $reversal = $ledger->reverse($journal);

        expect($journal->fresh()->status)->toBe('reversed')
            ->and($reversal->reverses_journal_id)->toBe($journal->id)
            ->and(accountBalance($tenant, ChartOfAccounts::BANK))->toBe(0)
            ->and(fn () => $ledger->reverse($journal->fresh()))->toThrow(DomainException::class)
            ->and(fn () => $ledger->reverse($reversal))->toThrow(DomainException::class);
    });

    test('refuses to post inside a locked period', function (): void {
        [, $tenant] = createFinanceContext();
        app(FinanceSettings::class)->for($tenant->id)->update(['lock_date' => today()->toDateString()]);
        $bank = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::BANK);
        $income = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::DEFAULT_INCOME);

        expect(fn () => app(LedgerService::class)->post($tenant->id, today(), [
            ['account_id' => $bank->id, 'debit' => 100],
            ['account_id' => $income->id, 'credit' => 100],
        ]))->toThrow(DomainException::class, 'locked');
    });

    test('refuses accounts from another organisation', function (): void {
        [, $tenant] = createFinanceContext();
        $other = Tenant::factory()->create();
        $foreign = FinAccount::query()->create(['tenant_id' => $other->id, 'code' => '9999', 'name' => 'Foreign', 'type' => 'asset']);
        $income = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::DEFAULT_INCOME);

        expect(fn () => app(LedgerService::class)->post($tenant->id, today(), [
            ['account_id' => $foreign->id, 'debit' => 100],
            ['account_id' => $income->id, 'credit' => 100],
        ]))->toThrow(DomainException::class);
    });
});

describe('Finance invoicing', function (): void {
    test('issuing an invoice posts receivable against income and keeps the books balanced', function (): void {
        [, $tenant] = createFinanceContext();
        $invoice = issueInvoice($tenant, financeCustomer($tenant), 100_000, 2.5);

        expect($invoice->number)->toBe('INV-00001')
            ->and($invoice->status)->toBe('sent')
            ->and($invoice->total)->toBe(250_000)
            ->and(accountBalance($tenant, ChartOfAccounts::RECEIVABLE))->toBe(250_000)
            ->and(accountBalance($tenant, ChartOfAccounts::DEFAULT_INCOME))->toBe(-250_000);

        $trial = app(FinanceReports::class)->trialBalance($tenant->id);
        expect($trial['debit'])->toBe($trial['credit'])->and($trial['debit'])->toBe(250_000);
    });

    test('charges the combined Ghana rate only when tax is switched on', function (): void {
        [, $tenant] = createFinanceContext();
        $customer = financeCustomer($tenant);
        $tax = app(ChartOfAccounts::class)->seedGhanaTax($tenant->id);
        $tax->update(['is_active' => true]);

        $without = issueInvoice($tenant, $customer, 100_000, 1, ['tax_id' => $tax->id]);
        expect($without->tax_total)->toBe(0)->and($without->total)->toBe(100_000);

        app(FinanceSettings::class)->for($tenant->id)->update(['tax_enabled' => true]);
        $with = issueInvoice($tenant, $customer, 100_000, 1, ['tax_id' => $tax->id]);

        expect($with->tax_total)->toBe(20_000)
            ->and($with->total)->toBe(120_000)
            ->and(accountBalance($tenant, ChartOfAccounts::TAX_PAYABLE))->toBe(-20_000);
    });

    test('only drafts can be edited and a zero total cannot be issued', function (): void {
        [, $tenant] = createFinanceContext();
        $customer = financeCustomer($tenant);
        $service = app(InvoiceService::class);

        $draft = $service->saveDraft($tenant->id, $customer, ['issue_date' => today()->toDateString()], [['description' => 'Free', 'quantity' => 1, 'unit_price' => 0]]);
        expect(fn () => $service->send($draft))->toThrow(DomainException::class, 'zero total');

        $sent = issueInvoice($tenant, $customer);
        expect(fn () => $service->saveDraft($tenant->id, $customer, ['issue_date' => today()->toDateString()], [['description' => 'x', 'quantity' => 1, 'unit_price' => 1]], $sent))
            ->toThrow(DomainException::class, 'Only draft');
    });

    test('voiding reverses the ledger entry and is blocked once money is paid', function (): void {
        [, $tenant] = createFinanceContext();
        $customer = financeCustomer($tenant);
        $service = app(InvoiceService::class);

        $voidable = issueInvoice($tenant, $customer);
        $service->void($voidable);
        expect($voidable->fresh()->status)->toBe('void')->and(accountBalance($tenant, ChartOfAccounts::RECEIVABLE))->toBe(0);

        $paid = issueInvoice($tenant, $customer);
        app(PaymentService::class)->record($tenant->id, $customer, 10_000, today(), [$paid->id => 10_000]);
        expect(fn () => $service->void($paid->fresh()))->toThrow(DomainException::class);
    });

    test('a quote posts nothing until converted into an invoice draft', function (): void {
        [, $tenant] = createFinanceContext();
        $customer = financeCustomer($tenant);
        $service = app(InvoiceService::class);

        $quote = $service->saveDraft($tenant->id, $customer, ['type' => 'quote', 'issue_date' => today()->toDateString()], [['description' => 'Build', 'quantity' => 1, 'unit_price' => 500_000]]);
        $quote = $service->send($quote);

        expect($quote->number)->toBe('QUO-00001')->and(FinJournal::query()->count())->toBe(0);

        $invoice = $service->convertQuote($quote);
        expect($invoice->type)->toBe('invoice')->and($invoice->status)->toBe('draft')->and($invoice->total)->toBe(500_000)
            ->and($quote->fresh()->status)->toBe('accepted')
            ->and(fn () => $service->convertQuote($quote->fresh()))->toThrow(DomainException::class);
    });
});

describe('Finance payments', function (): void {
    test('a partial then full payment moves the invoice through partly paid to paid', function (): void {
        [, $tenant] = createFinanceContext();
        $customer = financeCustomer($tenant);
        $invoice = issueInvoice($tenant, $customer, 100_000);
        $payments = app(PaymentService::class);

        $payments->record($tenant->id, $customer, 40_000, today(), [$invoice->id => 40_000]);
        expect($invoice->fresh()->status)->toBe('partly_paid')->and($invoice->fresh()->balanceDue())->toBe(60_000);

        $payments->record($tenant->id, $customer, 60_000, today(), [$invoice->id => 60_000]);
        expect($invoice->fresh()->status)->toBe('paid')
            ->and(accountBalance($tenant, ChartOfAccounts::RECEIVABLE))->toBe(0)
            ->and(accountBalance($tenant, ChartOfAccounts::BANK))->toBe(100_000);
    });

    test('rejects over-allocation and another customer\'s invoice', function (): void {
        [, $tenant] = createFinanceContext();
        $customer = financeCustomer($tenant);
        $stranger = financeCustomer($tenant, 'Other Co', 'o@o.test');
        $invoice = issueInvoice($tenant, $customer, 10_000);
        $payments = app(PaymentService::class);

        expect(fn () => $payments->record($tenant->id, $customer, 20_000, today(), [$invoice->id => 20_000]))->toThrow(DomainException::class);
        expect(fn () => $payments->record($tenant->id, $stranger, 10_000, today(), [$invoice->id => 10_000]))->toThrow(DomainException::class);
        expect(fn () => $payments->record($tenant->id, $customer, 1_000, today(), [$invoice->id => 5_000]))->toThrow(DomainException::class);
        expect(FinPayment::query()->count())->toBe(0);
    });

    test('an unallocated payment stays as customer credit and voiding re-opens invoices', function (): void {
        [, $tenant] = createFinanceContext();
        $customer = financeCustomer($tenant);
        $invoice = issueInvoice($tenant, $customer, 10_000);
        $payments = app(PaymentService::class);

        $credit = $payments->record($tenant->id, $customer, 3_000, today(), []);
        expect(accountBalance($tenant, ChartOfAccounts::RECEIVABLE))->toBe(7_000);

        $payment = $payments->record($tenant->id, $customer, 10_000, today(), [$invoice->id => 10_000]);
        expect($invoice->fresh()->status)->toBe('paid');

        $payments->void($payment);
        expect($invoice->fresh()->status)->toBe('sent')->and($invoice->fresh()->amount_paid)->toBe(0)
            ->and(FinPayment::query()->whereKey($payment->id)->exists())->toBeFalse()
            ->and(FinPayment::query()->whereKey($credit->id)->exists())->toBeTrue();
    });

    test('the same gateway reference is only recorded once', function (): void {
        [, $tenant] = createFinanceContext();
        $customer = financeCustomer($tenant);
        $invoice = issueInvoice($tenant, $customer, 10_000);
        $payments = app(PaymentService::class);
        $details = ['gateway' => 'paystack', 'gateway_reference' => 'ref_123'];

        $first = $payments->record($tenant->id, $customer, 10_000, today(), [$invoice->id => 10_000], $details);
        $second = $payments->record($tenant->id, $customer, 10_000, today(), [], $details);

        expect($second->id)->toBe($first->id)->and(FinPayment::query()->count())->toBe(1)
            ->and(accountBalance($tenant, ChartOfAccounts::GATEWAY_CLEARING))->toBe(10_000);
    });
});

describe('Finance recurring invoices', function (): void {
    test('creates due invoices, advances the schedule and stops after the end date', function (): void {
        [, $tenant] = createFinanceContext();
        $customer = financeCustomer($tenant);
        $template = FinRecurringInvoice::query()->create([
            'tenant_id' => $tenant->id,
            'contact_id' => $customer->id,
            'name' => 'Monthly hosting',
            'frequency' => 'monthly',
            'next_run_date' => today()->toDateString(),
            'end_date' => today()->addDays(10)->toDateString(),
            'auto_send' => true,
            'lines' => [['description' => 'Hosting', 'quantity' => 1, 'unit_price' => 25_000, 'tax_id' => null]],
        ]);

        expect(app(RecurringInvoiceService::class)->runDue())->toBe(1);

        $invoice = FinInvoice::query()->where('recurring_id', $template->id)->firstOrFail();
        expect($invoice->status)->toBe('sent')->and($invoice->total)->toBe(25_000);

        $template->refresh();
        expect($template->next_run_date->toDateString())->toBe(today()->addMonthNoOverflow()->toDateString())
            ->and($template->is_active)->toBeFalse()
            ->and(app(RecurringInvoiceService::class)->runDue())->toBe(0);
    });

    test('leaves a draft when auto-send is off', function (): void {
        [, $tenant] = createFinanceContext();
        FinRecurringInvoice::query()->create([
            'tenant_id' => $tenant->id,
            'contact_id' => financeCustomer($tenant)->id,
            'name' => 'Retainer',
            'frequency' => 'weekly',
            'next_run_date' => today()->toDateString(),
            'auto_send' => false,
            'lines' => [['description' => 'Retainer', 'quantity' => 1, 'unit_price' => 1_000, 'tax_id' => null]],
        ]);

        app(RecurringInvoiceService::class)->runDue();

        expect(FinInvoice::query()->firstOrFail()->status)->toBe('draft');
    });
});

describe('Finance web screens', function (): void {
    test('require the finance permission', function (): void {
        [$user] = createFinanceContext(false);

        $this->actingAs($user)->get(route('finance.index'))->assertForbidden();
        $this->actingAs($user)->post(route('finance.contacts.store'), ['name' => 'X'])->assertForbidden();
    });

    test('lets a user add a customer, draft, issue and pay an invoice through the screens', function (): void {
        [$user, $tenant] = createFinanceContext();
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->get(route('finance.index'))->assertOk()->assertSee('Finance');
        $this->post(route('finance.contacts.store'), ['name' => 'Kofi Ltd', 'email' => 'kofi@example.test'])->assertRedirect();
        $contact = FinContact::query()->where('name', 'Kofi Ltd')->firstOrFail();

        $this->post(route('finance.invoices.store'), [
            'type' => 'invoice',
            'contact_id' => $contact->id,
            'issue_date' => today()->toDateString(),
            'lines' => [['description' => 'Website', 'quantity' => 2, 'unit_price' => '1250.50']],
        ])->assertRedirect();

        $invoice = FinInvoice::query()->firstOrFail();
        expect($invoice->total)->toBe(250_100)->and($invoice->status)->toBe('draft');

        $this->get(route('finance.invoices.show', $invoice->uuid))->assertOk();
        $this->post(route('finance.invoices.send', $invoice->uuid))->assertRedirect();
        expect($invoice->fresh()->number)->toBe('INV-00001');

        $this->post(route('finance.payments.store'), [
            'contact_id' => $contact->id,
            'date' => today()->toDateString(),
            'amount' => '2501.00',
            'method' => 'bank_transfer',
            'allocations' => [$invoice->id => '2501.00'],
        ])->assertRedirect(route('finance.payments.index'));

        expect($invoice->fresh()->status)->toBe('paid');

        foreach (['finance.accounts.index', 'finance.journals.index', 'finance.journals.create', 'finance.contacts.index', 'finance.items.index', 'finance.invoices.index', 'finance.payments.index', 'finance.recurring.index', 'finance.reports.aged-receivables', 'finance.reports.trial-balance', 'finance.settings.edit'] as $name) {
            $this->get(route($name))->assertOk();
        }
    });

    test('shows a manual journal error when it does not balance and keeps the books unchanged', function (): void {
        [$user, $tenant] = createFinanceContext();
        $chart = app(ChartOfAccounts::class);
        $bank = $chart->bySystemKey($tenant->id, ChartOfAccounts::BANK);
        $income = $chart->bySystemKey($tenant->id, ChartOfAccounts::DEFAULT_INCOME);

        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('finance.journals.store'), [
                'date' => today()->toDateString(),
                'lines' => [
                    ['account_id' => $bank->id, 'debit' => '100'],
                    ['account_id' => $income->id, 'credit' => '90'],
                ],
            ])->assertSessionHas('error');

        expect(FinJournal::query()->count())->toBe(0);
    });

    test('never shows another organisation\'s documents', function (): void {
        [$user, $tenant] = createFinanceContext();
        $other = Tenant::factory()->create();
        $foreignContact = FinContact::query()->create(['tenant_id' => $other->id, 'name' => 'Foreign']);
        $foreign = FinInvoice::query()->create([
            'tenant_id' => $other->id, 'contact_id' => $foreignContact->id, 'issue_date' => today(), 'status' => 'sent', 'number' => 'INV-X',
        ]);

        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('finance.invoices.show', $foreign->uuid))->assertNotFound();
    });
});

describe('Finance online payments', function (): void {
    function connectPaystack(Tenant $tenant, string $secret = 'sk_test_finance'): void
    {
        TenantPaymentGateway::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'provider' => 'paystack', 'api_key_encrypted' => $secret, 'public_key_encrypted' => 'pk_test', 'is_active' => true,
        ]);
    }

    test('the public invoice page needs a valid signature and offers payment only when Paystack is connected', function (): void {
        [, $tenant] = createFinanceContext();
        $invoice = issueInvoice($tenant, financeCustomer($tenant));
        $online = app(OnlinePaymentService::class);

        $this->get(route('finance.public.invoice', ['tenant' => $tenant->id, 'invoice' => $invoice->uuid]))->assertForbidden();

        $this->get($online->paymentUrl($invoice))->assertOk()->assertSee($invoice->number)->assertDontSee('>Pay GHS', false);

        connectPaystack($tenant);
        $this->get($online->paymentUrl($invoice))->assertOk()->assertSee('Pay GHS 1,000.00');
    });

    test('a verified Paystack payment is recorded once, however many times it is reported', function (): void {
        [, $tenant] = createFinanceContext();
        connectPaystack($tenant);
        $customer = financeCustomer($tenant);
        $invoice = issueInvoice($tenant, $customer, 100_000);

        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['data' => [
            'status' => 'success', 'amount' => 100_000, 'currency' => 'GHS', 'reference' => 'ref_abc',
            'metadata' => ['type' => 'finance_invoice', 'tenant_id' => $tenant->id, 'finance_invoice_uuid' => $invoice->uuid],
        ]])]);

        $online = app(OnlinePaymentService::class);
        $online->recordVerified($tenant->id, 'ref_abc');
        $online->recordVerified($tenant->id, 'ref_abc');

        expect(FinPayment::query()->count())->toBe(1)
            ->and($invoice->fresh()->status)->toBe('paid')
            ->and(accountBalance($tenant, ChartOfAccounts::GATEWAY_CLEARING))->toBe(100_000);
    });

    test('a payment whose metadata names a different organisation or currency is ignored', function (): void {
        [, $tenant] = createFinanceContext();
        connectPaystack($tenant);
        $invoice = issueInvoice($tenant, financeCustomer($tenant), 100_000);
        $online = app(OnlinePaymentService::class);

        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['data' => [
            'status' => 'success', 'amount' => 100_000, 'currency' => 'GHS', 'reference' => 'ref_x',
            'metadata' => ['type' => 'finance_invoice', 'tenant_id' => 'some-other-tenant', 'finance_invoice_uuid' => $invoice->uuid],
        ]])]);
        expect($online->recordVerified($tenant->id, 'ref_x'))->toBeNull();

        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['data' => [
            'status' => 'success', 'amount' => 100_000, 'currency' => 'USD', 'reference' => 'ref_y',
            'metadata' => ['type' => 'finance_invoice', 'tenant_id' => $tenant->id, 'finance_invoice_uuid' => $invoice->uuid],
        ]])]);
        expect($online->recordVerified($tenant->id, 'ref_y'))->toBeNull()->and(FinPayment::query()->count())->toBe(0);
    });

    test('the webhook rejects a bad signature and accepts a correctly signed event', function (): void {
        [, $tenant] = createFinanceContext();
        connectPaystack($tenant, 'sk_test_finance');
        $invoice = issueInvoice($tenant, financeCustomer($tenant), 50_000);

        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['data' => [
            'status' => 'success', 'amount' => 50_000, 'currency' => 'GHS', 'reference' => 'ref_hook',
            'metadata' => ['type' => 'finance_invoice', 'tenant_id' => $tenant->id, 'finance_invoice_uuid' => $invoice->uuid],
        ]])]);

        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'ref_hook']]);
        $url = route('finance.webhooks.paystack', ['tenant' => $tenant->id]);

        $this->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => 'bad'], $body)->assertStatus(400);
        expect(FinPayment::query()->count())->toBe(0);

        $signature = hash_hmac('sha512', $body, 'sk_test_finance');
        $this->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature], $body)->assertOk();

        expect(FinPayment::query()->count())->toBe(1)->and($invoice->fresh()->status)->toBe('paid');
    });
});

describe('Finance reports', function (): void {
    test('aged receivables buckets open balances by lateness', function (): void {
        [, $tenant] = createFinanceContext();
        $customer = financeCustomer($tenant);
        $current = issueInvoice($tenant, $customer, 10_000);
        $late = issueInvoice($tenant, $customer, 20_000);
        $late->update(['due_date' => today()->subDays(45)]);

        $report = app(FinanceReports::class)->agedReceivables($tenant->id);

        expect($report['totals']['current'])->toBe(10_000)
            ->and($report['totals']['31_60'])->toBe(20_000)
            ->and($report['totals']['total'])->toBe(30_000);
    });

    test('tax rates are tenant specific', function (): void {
        [, $tenant] = createFinanceContext();
        $other = Tenant::factory()->create();
        app(ChartOfAccounts::class)->seedGhanaTax($tenant->id);

        expect(FinTax::query()->withoutGlobalScopes()->where('tenant_id', $other->id)->count())->toBe(0)
            ->and(FinTax::query()->count())->toBe(1);
    });
});

describe('Finance email', function (): void {
    test('emails an issued invoice with its payment link and refuses drafts', function (): void {
        Illuminate\Support\Facades\Mail::fake();
        [$user, $tenant] = createFinanceContext();
        $customer = financeCustomer($tenant);
        $invoice = issueInvoice($tenant, $customer);
        $draft = app(InvoiceService::class)->saveDraft($tenant->id, $customer, ['issue_date' => today()->toDateString()], [['description' => 'x', 'quantity' => 1, 'unit_price' => 100]]);

        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->post(route('finance.invoices.email', $draft->uuid))->assertSessionHas('error');
        Illuminate\Support\Facades\Mail::assertNothingSent();

        $this->post(route('finance.invoices.email', $invoice->uuid))->assertSessionHas('success');
        Illuminate\Support\Facades\Mail::assertSent(App\Modules\Finance\Mail\FinanceInvoiceMail::class, fn ($mail): bool => $mail->hasTo('pay@acme.test') && str_contains($mail->viewUrl, 'finance-pay'));
    });
});

describe('Finance routing', function (): void {
    test('the module home does not collide with the tenant revenue page at /finance', function (): void {
        expect(route('finance.index', [], false))->toBe('/finance/overview');
    });
});

describe('Finance PDF', function (): void {
    test('renders a PDF for staff and for the signed customer link only', function (): void {
        [$user, $tenant] = createFinanceContext();
        $invoice = issueInvoice($tenant, financeCustomer($tenant));

        $staff = $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id])->get(route('finance.invoices.pdf', $invoice->uuid));
        $staff->assertOk()->assertHeader('Content-Type', 'application/pdf');
        expect(str_starts_with($staff->getContent(), '%PDF'))->toBeTrue();

        $this->get(route('finance.public.pdf', ['tenant' => $tenant->id, 'invoice' => $invoice->uuid]))->assertForbidden();

        $signed = app(OnlinePaymentService::class)->pdfUrl($invoice);
        $public = $this->get($signed);
        $public->assertOk();
        expect(str_starts_with($public->getContent(), '%PDF'))->toBeTrue();
    });
});

describe('Finance credit notes', function (): void {
    function partialCredit(Tenant $tenant, FinContact $customer, FinInvoice $invoice, int $amount): FinInvoice
    {
        $service = app(InvoiceService::class);
        $note = $service->createCreditNote($invoice->fresh());

        return $service->saveDraft($tenant->id, $customer, ['type' => 'credit_note', 'issue_date' => today()->toDateString()], [
            ['description' => 'Discount', 'quantity' => 1, 'unit_price' => $amount],
        ], $note);
    }

    test('a credit note reduces what is owed, posts the reverse entry and can be voided', function (): void {
        [, $tenant] = createFinanceContext();
        $customer = financeCustomer($tenant);
        $invoice = issueInvoice($tenant, $customer, 100_000);
        $service = app(InvoiceService::class);

        $note = $service->send(partialCredit($tenant, $customer, $invoice, 40_000));

        expect($note->number)->toBe('CN-00001')->and($note->status)->toBe('applied')->and($note->credit_for_id)->toBe($invoice->id)
            ->and($invoice->fresh()->balanceDue())->toBe(60_000)
            ->and($invoice->fresh()->status)->toBe('partly_paid')
            ->and(accountBalance($tenant, ChartOfAccounts::RECEIVABLE))->toBe(60_000)
            ->and(accountBalance($tenant, ChartOfAccounts::DEFAULT_INCOME))->toBe(-60_000)
            ->and(fn () => $service->void($invoice->fresh()))->toThrow(DomainException::class, 'credit notes');

        $service->void($note->fresh());

        expect($invoice->fresh()->balanceDue())->toBe(100_000)->and($invoice->fresh()->status)->toBe('sent')
            ->and(accountBalance($tenant, ChartOfAccounts::RECEIVABLE))->toBe(100_000);

        $trial = app(FinanceReports::class)->trialBalance($tenant->id);
        expect($trial['debit'])->toBe($trial['credit']);
    });

    test('crediting the full balance settles the invoice and a larger credit is refused', function (): void {
        [, $tenant] = createFinanceContext();
        $customer = financeCustomer($tenant);
        $invoice = issueInvoice($tenant, $customer, 100_000);
        $service = app(InvoiceService::class);
        app(PaymentService::class)->record($tenant->id, $customer, 30_000, today(), [$invoice->id => 30_000]);

        expect(fn () => $service->send(partialCredit($tenant, $customer, $invoice, 80_000)))->toThrow(DomainException::class, 'more than the amount still owed');

        $service->send(partialCredit($tenant, $customer, $invoice, 70_000));
        expect($invoice->fresh()->status)->toBe('paid')->and($invoice->fresh()->balanceDue())->toBe(0)
            ->and(fn () => $service->createCreditNote($invoice->fresh()))->toThrow(DomainException::class);
    });

    test('the screen creates a credit note draft from an invoice', function (): void {
        [$user, $tenant] = createFinanceContext();
        $invoice = issueInvoice($tenant, financeCustomer($tenant), 50_000);

        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('finance.invoices.credit-note', $invoice->uuid))->assertRedirect();

        $note = FinInvoice::query()->where('type', 'credit_note')->firstOrFail();
        expect($note->status)->toBe('draft')->and($note->total)->toBe(50_000)->and($note->credit_for_id)->toBe($invoice->id);

        $this->get(route('finance.invoices.edit', $note->uuid))->assertOk();
        $this->get(route('finance.invoices.index', ['type' => 'credit_note']))->assertOk()->assertSee('Credit notes');
    });
});

function codeBalance(Tenant $tenant, string $code): int
{
    $account = FinAccount::query()->where('tenant_id', $tenant->id)->where('code', $code)->firstOrFail();
    $row = DB::connection('tenant')->table('fin_journal_lines')->where('account_id', $account->id)
        ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')->first();

    return (int) $row->d - (int) $row->c;
}

function financeSupplier(Tenant $tenant, string $name = 'Cloud Host Ltd'): FinContact
{
    return FinContact::query()->create(['tenant_id' => $tenant->id, 'name' => $name, 'is_customer' => false, 'is_vendor' => true]);
}

function draftBill(Tenant $tenant, FinContact $supplier, int $amount = 80_000, string $accountKey = '6200'): FinBill
{
    $account = FinAccount::query()->where('tenant_id', $tenant->id)->where('code', $accountKey)->firstOrFail();

    return app(BillService::class)->saveDraft(
        $tenant->id,
        $supplier,
        ['issue_date' => today()->toDateString(), 'vendor_reference' => 'SUP-77'],
        [['description' => 'Hosting', 'amount' => $amount, 'account_id' => $account->id]],
    );
}

describe('Finance bills and expenses', function (): void {
    test('approving a bill debits the expense and credits payables, and the books stay balanced', function (): void {
        [, $tenant] = createFinanceContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $bill = app(BillService::class)->approve(draftBill($tenant, financeSupplier($tenant)));

        expect($bill->number)->toBe('BILL-00001')->and($bill->status)->toBe('approved')->and($bill->due_date)->not->toBeNull()
            ->and(accountBalance($tenant, ChartOfAccounts::PAYABLE))->toBe(-80_000);

        $trial = app(FinanceReports::class)->trialBalance($tenant->id);
        expect($trial['debit'])->toBe($trial['credit'])->and($trial['debit'])->toBe(80_000);
    });

    test('only draft bills can be edited and a zero bill cannot be approved', function (): void {
        [, $tenant] = createFinanceContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $service = app(BillService::class);
        $bill = $service->approve(draftBill($tenant, financeSupplier($tenant)));

        expect(fn () => $service->saveDraft($tenant->id, $bill->contact, ['issue_date' => today()->toDateString()], [['description' => 'x', 'amount' => 1, 'account_id' => 1]], $bill))
            ->toThrow(DomainException::class, 'Only draft')
            ->and(fn () => $service->approve($bill))->toThrow(DomainException::class);
    });

    test('paying a bill in parts settles it and refuses overpayment', function (): void {
        [, $tenant] = createFinanceContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $service = app(BillService::class);
        $bank = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::BANK);
        $bill = $service->approve(draftBill($tenant, financeSupplier($tenant), 100_000));

        $service->pay($bill, 40_000, today(), $bank->id);
        expect($bill->fresh()->status)->toBe('partly_paid')->and($bill->fresh()->balanceDue())->toBe(60_000);

        expect(fn () => $service->pay($bill->fresh(), 70_000, today(), $bank->id))->toThrow(DomainException::class, 'no more than the balance');

        $service->pay($bill->fresh(), 60_000, today(), $bank->id);
        expect($bill->fresh()->status)->toBe('paid')
            ->and(accountBalance($tenant, ChartOfAccounts::PAYABLE))->toBe(0)
            ->and(accountBalance($tenant, ChartOfAccounts::BANK))->toBe(-100_000)
            ->and(fn () => $service->pay($bill->fresh(), 1, today(), $bank->id))->toThrow(DomainException::class);
    });

    test('voiding a payment re-opens the bill and voiding a bill needs its payments voided first', function (): void {
        [, $tenant] = createFinanceContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $service = app(BillService::class);
        $bank = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::BANK);
        $bill = $service->approve(draftBill($tenant, financeSupplier($tenant)));
        $payment = $service->pay($bill, 80_000, today(), $bank->id);

        expect(fn () => $service->void($bill->fresh()))->toThrow(DomainException::class, 'Void the payments first');

        $service->voidPayment($payment);
        expect($bill->fresh()->status)->toBe('approved')->and($bill->fresh()->amount_paid)->toBe(0)
            ->and(fn () => $service->voidPayment($payment->fresh()))->toThrow(DomainException::class);

        $service->void($bill->fresh());
        expect($bill->fresh()->status)->toBe('void')
            ->and(accountBalance($tenant, ChartOfAccounts::PAYABLE))->toBe(0)
            ->and(accountBalance($tenant, ChartOfAccounts::BANK))->toBe(0);
    });

    test('an expense moves money from an asset account to an expense account and can be voided', function (): void {
        [, $tenant] = createFinanceContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $service = app(ExpenseService::class);
        $expenseAccount = FinAccount::query()->where('tenant_id', $tenant->id)->where('code', '6300')->firstOrFail();
        $bank = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::BANK);

        expect(fn () => $service->record($tenant->id, 0, today(), $expenseAccount->id, $bank->id))->toThrow(DomainException::class);

        $expense = $service->record($tenant->id, 25_000, today(), $expenseAccount->id, $bank->id, null, ['description' => 'Flyers']);
        expect($expense->number)->toBe('EXP-00001')
            ->and(codeBalance($tenant, '6300'))->toBe(25_000)
            ->and(accountBalance($tenant, ChartOfAccounts::BANK))->toBe(-25_000);

        $service->void($expense);
        expect(codeBalance($tenant, '6300'))->toBe(0);
    });

    test('aged payables lists open bills and totals what is owed', function (): void {
        [, $tenant] = createFinanceContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $service = app(BillService::class);
        $service->approve(draftBill($tenant, financeSupplier($tenant), 50_000));
        $paid = $service->approve(draftBill($tenant, financeSupplier($tenant, 'Other'), 30_000));
        $service->pay($paid, 30_000, today(), app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::BANK)->id);

        $report = app(FinanceReports::class)->agedPayables($tenant->id);
        expect($report['rows'])->toHaveCount(1)->and($report['totals']['total'])->toBe(50_000);
    });

    test('the screens create, approve and pay a bill and record an expense', function (): void {
        [$user, $tenant] = createFinanceContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $supplier = financeSupplier($tenant);
        $account = FinAccount::query()->where('tenant_id', $tenant->id)->where('code', '6200')->firstOrFail();
        $bank = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::BANK);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->get(route('finance.bills.index'))->assertOk();
        $this->get(route('finance.bills.create'))->assertOk()->assertSee('Cloud Host Ltd');
        $this->post(route('finance.bills.store'), [
            'contact_id' => $supplier->id,
            'issue_date' => today()->toDateString(),
            'lines' => [['description' => 'Servers', 'amount' => '500.00', 'account_id' => $account->id]],
        ])->assertRedirect();

        $bill = FinBill::query()->firstOrFail();
        expect($bill->total)->toBe(50_000)->and($bill->status)->toBe('draft');
        $this->get(route('finance.bills.edit', $bill->uuid))->assertOk();
        $this->post(route('finance.bills.approve', $bill->uuid))->assertRedirect();
        $this->get(route('finance.bills.show', $bill->uuid))->assertOk()->assertSee('BILL-00001');
        $this->post(route('finance.bills.pay', $bill->uuid), [
            'date' => today()->toDateString(), 'amount' => '500.00', 'method' => 'bank_transfer', 'paid_from_account_id' => $bank->id,
        ])->assertRedirect();
        expect($bill->fresh()->status)->toBe('paid');

        $this->get(route('finance.expenses.index'))->assertOk();
        $this->post(route('finance.expenses.store'), [
            'date' => today()->toDateString(), 'amount' => '20.00', 'account_id' => $account->id, 'paid_from_account_id' => $bank->id,
        ])->assertRedirect();
        expect(FinExpense::query()->count())->toBe(1);
        $this->get(route('finance.reports.aged-payables'))->assertOk();
        $this->get(route('finance.contacts.index', ['role' => 'vendor']))->assertOk()->assertSee('Cloud Host Ltd');
    });

    test('a bill must be for a supplier and users without the permission are blocked', function (): void {
        [$user, $tenant] = createFinanceContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $customer = financeCustomer($tenant);
        $account = FinAccount::query()->where('tenant_id', $tenant->id)->where('code', '6200')->firstOrFail();
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->post(route('finance.bills.store'), [
            'contact_id' => $customer->id, 'issue_date' => today()->toDateString(),
            'lines' => [['description' => 'x', 'amount' => '5', 'account_id' => $account->id]],
        ])->assertSessionHasErrors('contact_id');

        $user->revokePermissionTo(['finance.bills.manage', 'finance.expenses.manage']);
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->get(route('finance.bills.create'))->assertForbidden();
        $this->post(route('finance.expenses.store'), [])->assertForbidden();
    });
});

describe('Finance demo seeder', function (): void {
    test('creates sample books that balance, and only once', function (): void {
        [, $tenant] = createFinanceContext();
        config(['app.demo_tenant' => $tenant->slug]);

        $this->seed(App\Modules\Finance\Database\Seeders\FinanceDemoSeeder::class);

        $statuses = FinInvoice::query()->pluck('status')->all();
        expect($statuses)->toContain('paid', 'partly_paid', 'sent', 'draft')
            ->and(FinBill::query()->pluck('status')->all())->toContain('paid', 'partly_paid', 'approved', 'draft')
            ->and(FinExpense::query()->count())->toBe(2)
            ->and(FinContact::query()->where('is_vendor', true)->count())->toBe(3);

        $trial = app(FinanceReports::class)->trialBalance($tenant->id);
        expect($trial['debit'])->toBe($trial['credit'])
            ->and(app(FinanceReports::class)->agedReceivables($tenant->id)['totals']['total'])->toBeGreaterThan(0)
            ->and(app(FinanceReports::class)->agedPayables($tenant->id)['totals']['total'])->toBeGreaterThan(0);

        $count = FinJournal::query()->count();
        $this->seed(App\Modules\Finance\Database\Seeders\FinanceDemoSeeder::class);
        expect(FinJournal::query()->count())->toBe($count);
    });

    test('refuses to run in production', function (): void {
        [, $tenant] = createFinanceContext();
        config(['app.demo_tenant' => $tenant->slug]);
        app()->detectEnvironment(fn (): string => 'production');

        (new App\Modules\Finance\Database\Seeders\FinanceDemoSeeder)->run();
        app()->detectEnvironment(fn (): string => 'testing');

        expect(FinContact::query()->count())->toBe(0);
    });
});
