<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantPaymentGateway;
use App\Models\User;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinBankAccount;
use App\Modules\Finance\Models\FinBankTransaction;
use App\Modules\Finance\Models\FinBill;
use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Models\FinExpense;
use App\Modules\Finance\Models\FinFund;
use App\Modules\Finance\Models\FinInvoice;
use App\Modules\Finance\Models\FinJournal;
use App\Modules\Finance\Models\FinPayment;
use App\Modules\Finance\Models\FinRecurringInvoice;
use App\Modules\Finance\Models\FinTax;
use App\Modules\Finance\Services\BankReconciliation;
use App\Modules\Finance\Services\BankStatementImporter;
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
use App\Modules\Finance\Services\StatementReports;
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
            ->and(FinContact::query()->where('is_vendor', true)->count())->toBe(3)
            ->and(FinBankTransaction::query()->where('status', 'matched')->count())->toBe(4)
            ->and(FinBankTransaction::query()->where('status', 'unmatched')->count())->toBe(2);

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

function bankFor(Tenant $tenant): FinBankAccount
{
    app(ChartOfAccounts::class)->ensureSeeded($tenant->id);

    return FinBankAccount::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Main account',
        'account_id' => app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::BANK)->id,
    ]);
}

function importStatement(FinBankAccount $bank, string $csv): array
{
    $path = tempnam(sys_get_temp_dir(), 'stmt');
    file_put_contents($path, $csv);

    try {
        return app(BankStatementImporter::class)->import($bank, $path);
    } finally {
        unlink($path);
    }
}

describe('Finance banking', function (): void {
    test('imports signed amounts and debit/credit columns, skipping repeats and reporting bad rows', function (): void {
        [, $tenant] = createFinanceContext();
        $bank = bankFor($tenant);

        $result = importStatement($bank, "Date,Description,Amount\n2026-11-02,Transfer in,\"1,250.50\"\n02/11/2026,Card fee,-12.00\nnot-a-date,Oops,5\n2026-11-03,Zero,0\n");
        expect($result['imported'])->toBe(2)->and($result['errors'])->toHaveCount(2)
            ->and(FinBankTransaction::query()->orderBy('id')->pluck('amount')->all())->toBe([125_050, -1_200]);

        $again = importStatement($bank, "Date,Description,Amount\n2026-11-02,Transfer in,\"1,250.50\"\n02/11/2026,Card fee,-12.00\n");
        expect($again['imported'])->toBe(0)->and($again['skipped'])->toBe(2);

        $split = importStatement($bank, "Transaction Date,Narration,Withdrawal,Deposit\n2026-11-05,Rent,500.00,\n2026-11-06,Customer,,300.00\n");
        expect($split['imported'])->toBe(2)
            ->and(FinBankTransaction::query()->where('description', 'Rent')->value('amount'))->toBe(-50_000)
            ->and(FinBankTransaction::query()->where('description', 'Customer')->value('amount'))->toBe(30_000);

        expect(fn () => importStatement($bank, "Foo,Bar\n1,2\n"))->toThrow(DomainException::class, 'Date column');
    });

    test('two identical lines in one statement are both kept', function (): void {
        [, $tenant] = createFinanceContext();
        $bank = bankFor($tenant);

        $result = importStatement($bank, "Date,Description,Amount\n2026-11-02,Bus fare,-5.00\n2026-11-02,Bus fare,-5.00\n");
        expect($result['imported'])->toBe(2)->and(importStatement($bank, "Date,Description,Amount\n2026-11-02,Bus fare,-5.00\n2026-11-02,Bus fare,-5.00\n")['imported'])->toBe(0);
    });

    test('suggests and applies matches by amount and date, using each ledger entry once', function (): void {
        [, $tenant] = createFinanceContext();
        $bank = bankFor($tenant);
        $customer = financeCustomer($tenant);
        $invoice = issueInvoice($tenant, $customer, 100_000);
        app(PaymentService::class)->record($tenant->id, $customer, 100_000, today()->subDays(3), [$invoice->id => 100_000]);

        importStatement($bank, "Date,Description,Amount\n".today()->subDays(2)->toDateString().",ACME payment,1000.00\n".today()->subDays(2)->toDateString().",Second same amount,1000.00\n".today()->toDateString().",Unrelated,77.00\n");
        $reconciliation = app(BankReconciliation::class);

        expect($reconciliation->suggestions($bank))->toHaveCount(1)
            ->and($reconciliation->autoMatch($bank))->toBe(1)
            ->and(FinBankTransaction::query()->where('status', 'matched')->count())->toBe(1)
            ->and(FinBankTransaction::query()->where('status', 'unmatched')->count())->toBe(2);
    });

    test('a manual match needs the same amount and an available ledger entry', function (): void {
        [, $tenant] = createFinanceContext();
        $bank = bankFor($tenant);
        $customer = financeCustomer($tenant);
        $invoice = issueInvoice($tenant, $customer, 100_000);
        app(PaymentService::class)->record($tenant->id, $customer, 100_000, today(), [$invoice->id => 100_000]);
        importStatement($bank, "Date,Description,Amount\n".today()->toDateString().",Deposit,999.00\n".today()->toDateString().",Deposit 2,1000.00\n");
        $reconciliation = app(BankReconciliation::class);
        $line = $reconciliation->unmatchedLedgerLines($bank)->firstOrFail();
        [$wrong, $right] = FinBankTransaction::query()->orderBy('id')->get()->all();

        expect(fn () => $reconciliation->match($wrong, $line->id))->toThrow(DomainException::class, 'amounts differ');

        $reconciliation->match($right, $line->id);
        expect($right->fresh()->status)->toBe('matched')
            ->and(fn () => $reconciliation->match($right->fresh(), $line->id))->toThrow(DomainException::class);

        $reconciliation->unmatch($right->fresh());
        expect($right->fresh()->status)->toBe('unmatched')->and($reconciliation->unmatchedLedgerLines($bank)->count())->toBe(1);
    });

    test('statement lines with no entry can be booked as an expense or a deposit and are matched at once', function (): void {
        [, $tenant] = createFinanceContext();
        $bank = bankFor($tenant);
        importStatement($bank, "Date,Description,Amount\n".today()->toDateString().",MTN charges,-20.00\n".today()->toDateString().",Grant received,400.00\n");
        $reconciliation = app(BankReconciliation::class);
        [$out, $in] = FinBankTransaction::query()->orderBy('id')->get()->all();
        $expenseAccount = FinAccount::query()->where('tenant_id', $tenant->id)->where('code', '6400')->firstOrFail();
        $income = FinAccount::query()->where('tenant_id', $tenant->id)->where('code', '4100')->firstOrFail();

        expect(fn () => $reconciliation->recordFromStatement($out, $income->id))->toThrow(DomainException::class, 'expense account')
            ->and(fn () => $reconciliation->recordFromStatement($in, $expenseAccount->id))->toThrow(DomainException::class, 'income, liability or equity');

        $reconciliation->recordFromStatement($out, $expenseAccount->id);
        $reconciliation->recordFromStatement($in, $income->id);

        expect(FinExpense::query()->count())->toBe(1)
            ->and(FinBankTransaction::query()->where('status', 'matched')->count())->toBe(2)
            ->and(accountBalance($tenant, ChartOfAccounts::BANK))->toBe(38_000);

        $summary = $reconciliation->summary($bank);
        expect($summary['is_reconciled'])->toBeTrue()->and($summary['difference'])->toBe(0);
        $trial = app(FinanceReports::class)->trialBalance($tenant->id);
        expect($trial['debit'])->toBe($trial['credit']);
    });

    test('the summary shows what is left to explain, and excluded lines stay out of matching', function (): void {
        [, $tenant] = createFinanceContext();
        $bank = bankFor($tenant);
        $bank->update(['opening_balance' => 100_000]);
        $customer = financeCustomer($tenant);
        $invoice = issueInvoice($tenant, $customer, 50_000);
        app(PaymentService::class)->record($tenant->id, $customer, 50_000, today(), [$invoice->id => 50_000]);
        importStatement($bank, "Date,Description,Amount\n".today()->toDateString().",Unknown,-30.00\n");
        $reconciliation = app(BankReconciliation::class);

        $summary = $reconciliation->summary($bank);
        expect($summary['ledger_balance'])->toBe(50_000)->and($summary['statement_balance'])->toBe(97_000)
            ->and($summary['unmatched_statement'])->toBe(1)->and($summary['outstanding_ledger'])->toBe(1)->and($summary['is_reconciled'])->toBeFalse();

        $line = FinBankTransaction::query()->firstOrFail();
        $reconciliation->exclude($line);
        expect($line->fresh()->status)->toBe('excluded')->and(fn () => $reconciliation->match($line->fresh(), 1))->toThrow(DomainException::class);
        $reconciliation->restore($line->fresh());
        expect($line->fresh()->status)->toBe('unmatched');
    });

    test('a voided payment drops out of the ledger entries available to match', function (): void {
        [, $tenant] = createFinanceContext();
        $bank = bankFor($tenant);
        $customer = financeCustomer($tenant);
        $invoice = issueInvoice($tenant, $customer, 50_000);
        $payment = app(PaymentService::class)->record($tenant->id, $customer, 50_000, today(), [$invoice->id => 50_000]);
        expect(app(BankReconciliation::class)->unmatchedLedgerLines($bank)->count())->toBe(1);

        app(PaymentService::class)->void($payment);
        expect(app(BankReconciliation::class)->unmatchedLedgerLines($bank)->count())->toBe(0);
    });

    test('the banking screens add an account, import a file and match a line', function (): void {
        [$user, $tenant] = createFinanceContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $bankLedger = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::BANK);
        $customer = financeCustomer($tenant);
        $invoice = issueInvoice($tenant, $customer, 25_000);
        app(PaymentService::class)->record($tenant->id, $customer, 25_000, today(), [$invoice->id => 25_000]);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->get(route('finance.bank.index'))->assertOk();
        $this->post(route('finance.bank.store'), ['name' => 'GCB current', 'account_id' => $bankLedger->id, 'opening_balance' => '0'])->assertRedirect();
        $bank = FinBankAccount::query()->firstOrFail();
        $this->post(route('finance.bank.store'), ['name' => 'Again', 'account_id' => $bankLedger->id])->assertSessionHasErrors('account_id');

        $file = Illuminate\Http\UploadedFile::fake()->createWithContent('statement.csv', "Date,Description,Amount\n".today()->toDateString().",Payment,250.00\n");
        $this->post(route('finance.bank.import', $bank->uuid), ['statement' => $file])->assertRedirect()->assertSessionHas('success');
        $this->get(route('finance.bank.show', $bank->uuid))->assertOk()->assertSee('Payment')->assertSee('Match 1 suggested');

        $this->post(route('finance.bank.auto-match', $bank->uuid))->assertRedirect();
        expect(FinBankTransaction::query()->firstOrFail()->status)->toBe('matched');
        $this->get(route('finance.bank.show', [$bank->uuid, 'show' => 'matched']))->assertOk()->assertSee('JNL-');
        $this->post(route('finance.bank.unmatch', FinBankTransaction::query()->firstOrFail()->uuid))->assertRedirect();
        $this->get(route('finance.bank.index'))->assertOk()->assertSee('GCB current');
    });

    test('banking actions need the bank permission', function (): void {
        [$user, $tenant] = createFinanceContext();
        $bank = bankFor($tenant);
        $user->revokePermissionTo('finance.bank.manage');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->get(route('finance.bank.show', $bank->uuid))->assertOk();
        $this->post(route('finance.bank.auto-match', $bank->uuid))->assertForbidden();
        $this->post(route('finance.bank.import', $bank->uuid), [])->assertForbidden();
    });
});

/**
 * A prior-year loss and an owner's investment, then this year's sales, bill, expense and equipment purchase.
 *
 * @return array{0: Tenant, 1: FinFund}
 */
function statementBooks(): array
{
    [, $tenant] = createFinanceContext();
    app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
    $code = fn (string $accountCode): FinAccount => FinAccount::query()->where('tenant_id', $tenant->id)->where('code', $accountCode)->firstOrFail();
    $bank = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::BANK);
    $priorYear = today()->startOfYear()->subDays(10);
    $fund = FinFund::query()->create(['tenant_id' => $tenant->id, 'code' => 'YTH', 'name' => 'Youth fund', 'is_restricted' => true]);

    app(LedgerService::class)->post($tenant->id, $priorYear, [
        ['account_id' => $bank->id, 'debit' => 1_000_000],
        ['account_id' => $code('3000')->id, 'credit' => 1_000_000],
    ], 'Owner investment');
    app(ExpenseService::class)->record($tenant->id, 100_000, $priorYear, $code('6100')->id, $bank->id);

    $customer = financeCustomer($tenant);
    $invoice = issueInvoice($tenant, $customer, 300_000, 1, ['fund_id' => $fund->id]);
    app(PaymentService::class)->record($tenant->id, $customer, 200_000, today(), [$invoice->id => 200_000]);

    $bill = app(BillService::class)->approve(draftBill($tenant, financeSupplier($tenant), 80_000));
    app(BillService::class)->pay($bill, 30_000, today(), $bank->id);

    app(ExpenseService::class)->record($tenant->id, 20_000, today(), $code('6300')->id, $bank->id, null, ['fund_id' => $fund->id]);
    app(LedgerService::class)->post($tenant->id, today(), [
        ['account_id' => $code('1500')->id, 'debit' => 50_000],
        ['account_id' => $bank->id, 'credit' => 50_000],
    ], 'Laptop');

    return [$tenant, $fund];
}

describe('Finance statements', function (): void {
    test('profit and loss totals income and expenses for the period and can be limited to a fund', function (): void {
        [$tenant, $fund] = statementBooks();
        $reports = app(StatementReports::class);
        $from = $reports->fiscalYearStart($tenant->id, today());

        $all = $reports->profitAndLoss($tenant->id, $from, today());
        expect($all['total_income'])->toBe(300_000)->and($all['total_expenses'])->toBe(100_000)->and($all['net_profit'])->toBe(200_000);

        $youth = $reports->profitAndLoss($tenant->id, $from, today(), $fund->id);
        expect($youth['total_income'])->toBe(300_000)->and($youth['total_expenses'])->toBe(20_000);

        $lastYear = $reports->profitAndLoss($tenant->id, $from->subYear(), $from->subDay());
        expect($lastYear['net_profit'])->toBe(-100_000);
    });

    test('the balance sheet balances and splits this year from earlier years', function (): void {
        [$tenant] = statementBooks();
        $sheet = app(StatementReports::class)->balanceSheet($tenant->id, today());

        expect($sheet['total_assets'])->toBe(1_150_000)->and($sheet['total_liabilities'])->toBe(50_000)->and($sheet['total_equity'])->toBe(1_100_000)
            ->and($sheet['current_year_profit'])->toBe(200_000)->and($sheet['prior_years_profit'])->toBe(-100_000)->and($sheet['balanced'])->toBeTrue();

        $earlier = app(StatementReports::class)->balanceSheet($tenant->id, today()->startOfYear()->subDay());
        expect($earlier['balanced'])->toBeTrue()->and($earlier['total_assets'])->toBe(900_000);
    });

    test('cash flow explains the whole change in cash and files equipment as investing', function (): void {
        [$tenant] = statementBooks();
        $reports = app(StatementReports::class);
        $flow = $reports->cashFlow($tenant->id, $reports->fiscalYearStart($tenant->id, today()), today());

        expect($flow['opening'])->toBe(900_000)->and($flow['closing'])->toBe(1_000_000)->and($flow['net_change'])->toBe(100_000)
            ->and($flow['sections']['operating']['total'])->toBe(150_000)
            ->and($flow['sections']['investing']['total'])->toBe(-50_000)
            ->and($flow['sections']['financing']['total'])->toBe(0)
            ->and(array_sum(array_column($flow['sections'], 'total')))->toBe($flow['net_change']);
    });

    test('the fund report shows each fund, unfunded activity and running balances', function (): void {
        [$tenant, $fund] = statementBooks();
        $reports = app(StatementReports::class);
        $report = $reports->fundReport($tenant->id, $reports->fiscalYearStart($tenant->id, today()), today());
        $rows = collect($report['rows'])->keyBy('name');

        expect($rows['Youth fund']['income'])->toBe(300_000)->and($rows['Youth fund']['expenses'])->toBe(20_000)->and($rows['Youth fund']['balance'])->toBe(280_000)
            ->and($rows['No fund']['expenses'])->toBe(80_000)->and($rows['No fund']['balance'])->toBe(-180_000)
            ->and($report['totals']['balance'])->toBe(100_000);
    });

    test('the report screens open and reject a backwards date range', function (): void {
        [$user, $tenant] = createFinanceContext();
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        foreach (['profit-and-loss', 'balance-sheet', 'cash-flow', 'funds', 'aged-payables'] as $report) {
            $this->get(route('finance.reports.'.$report))->assertOk();
        }
        $this->get(route('finance.reports.profit-and-loss', ['from' => '2026-01-01', 'to' => '2026-12-31', 'fund_id' => '']))->assertOk()->assertSee('Total income');
        $this->get(route('finance.reports.balance-sheet', ['as_of' => '2026-06-30']))->assertOk()->assertSee('Total assets');
        $this->from(route('finance.reports.cash-flow'))->get(route('finance.reports.cash-flow', ['from' => '2026-12-31', 'to' => '2026-01-01']))->assertSessionHasErrors('to');
    });
});

describe('Finance exports and general ledger', function (): void {
    test('the general ledger lists postings with opening and running balances', function (): void {
        [$tenant] = statementBooks();
        $reports = app(StatementReports::class);
        $bank = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::BANK);
        $thisYear = $reports->fiscalYearStart($tenant->id, today());

        $ledger = $reports->generalLedger($tenant->id, $bank, $thisYear, today());

        expect($ledger['opening'])->toBe(900_000)->and($ledger['closing'])->toBe(1_000_000)
            ->and($ledger['total_debit'] - $ledger['total_credit'])->toBe(100_000)
            ->and(collect($ledger['lines'])->last()['balance'])->toBe($ledger['closing']);

        $payable = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::PAYABLE);
        $payables = $reports->generalLedger($tenant->id, $payable, $thisYear, today());
        expect($payables['closing'])->toBe(50_000);
    });

    test('every report can be downloaded as CSV and risky cells are neutralised', function (): void {
        [$tenant] = statementBooks();
        $user = User::query()->latest('id')->first();
        $bank = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::BANK);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        foreach (['profit-and-loss', 'balance-sheet', 'cash-flow', 'funds', 'aged-receivables', 'aged-payables', 'trial-balance'] as $report) {
            $response = $this->get(route('finance.reports.'.$report, ['format' => 'csv']))->assertOk();
            expect($response->headers->get('Content-Type'))->toContain('text/csv');
        }

        $content = $this->get(route('finance.reports.profit-and-loss', ['format' => 'csv']))->streamedContent();
        expect($content)->toContain('"Total income",3000.00')->and($content)->toContain('"Net profit",2000.00');

        $this->get(route('finance.reports.general-ledger', ['format' => 'csv', 'account_id' => $bank->id]))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->get(route('finance.reports.general-ledger'))->assertOk()->assertSee('Choose an account');
        $this->get(route('finance.reports.general-ledger', ['account_id' => $bank->id]))->assertOk()->assertSee('Opening balance');

        $rows = [['=HYPERLINK("http://x")', '+1', 'ok']];
        $csv = app(App\Modules\Finance\Services\ReportCsv::class)->download('x.csv', $rows);
        ob_start();
        $csv->sendContent();
        expect(ob_get_clean())->toContain("'=HYPERLINK")->and('')->toBe('');
    });
});

describe('Finance budgets', function (): void {
    test('budget against actual spreads the plan over the months and signs the variance for income and expense', function (): void {
        [$tenant] = statementBooks();
        $account = fn (string $code): FinAccount => FinAccount::query()->where('tenant_id', $tenant->id)->where('code', $code)->firstOrFail();
        $start = today()->startOfYear();
        $budget = App\Modules\Finance\Models\FinBudget::query()->create(['tenant_id' => $tenant->id, 'name' => 'Year', 'starts_on' => $start, 'ends_on' => $start->copy()->endOfYear()]);
        $budget->lines()->create(['account_id' => $account('4000')->id, 'amount' => 1_200_000]);
        $budget->lines()->create(['account_id' => $account('6200')->id, 'amount' => 120_000]);

        $asOf = $start->copy()->addMonths(5)->endOfMonth();
        $report = app(App\Modules\Finance\Services\BudgetReports::class)->compare($budget, $asOf);
        $rows = collect($report['rows'])->keyBy(fn (array $row): string => $row['line']->account->code);

        expect($report['total_months'])->toBe(12)->and($report['elapsed_months'])->toBe(6)
            ->and($rows['4000']['to_date'])->toBe(600_000)
            ->and($rows['6200']['to_date'])->toBe(60_000);

        $now = app(App\Modules\Finance\Services\BudgetReports::class)->compare($budget, today()->endOfYear());
        $now = collect($now['rows'])->keyBy(fn (array $row): string => $row['line']->account->code);
        expect($now['4000']['actual'])->toBe(300_000)->and($now['4000']['variance'])->toBe(300_000 - 1_200_000)
            ->and($now['6200']['actual'])->toBe(80_000)->and($now['6200']['variance'])->toBe(120_000 - 80_000);
    });

    test('the budget screens create, show and delete a budget and need the permission', function (): void {
        [$user, $tenant] = createFinanceContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $account = FinAccount::query()->where('tenant_id', $tenant->id)->where('code', '6300')->firstOrFail();
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->get(route('finance.budgets.index'))->assertOk();
        $this->post(route('finance.budgets.store'), ['name' => 'Marketing', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'lines' => [['account_id' => $account->id, 'amount' => '1200.00']]])->assertRedirect();
        $budget = App\Modules\Finance\Models\FinBudget::query()->firstOrFail();
        expect($budget->lines()->first()->amount)->toBe(120_000);

        $this->get(route('finance.budgets.show', $budget->uuid))->assertOk()->assertSee('Marketing');
        $this->post(route('finance.budgets.store'), ['name' => 'Bad', 'starts_on' => '2026-06-01', 'ends_on' => '2026-01-01', 'lines' => []])->assertSessionHasErrors(['ends_on', 'lines']);

        $user->revokePermissionTo('finance.budgets.manage');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->delete(route('finance.budgets.destroy', $budget->uuid))->assertForbidden();
        $user->givePermissionTo('finance.budgets.manage');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->delete(route('finance.budgets.destroy', $budget->uuid))->assertRedirect(route('finance.budgets.index'));
        expect(App\Modules\Finance\Models\FinBudget::query()->count())->toBe(0);
    });
});

describe('Finance alerts', function (): void {
    function financeAlertRule(Tenant $tenant, User $user, string $event): void
    {
        App\Models\AlertRule::query()->create([
            'tenant_id' => $tenant->id, 'name' => $event, 'event' => $event, 'is_active' => true,
            'channels' => ['database'], 'recipients' => [],
        ]);
    }

    test('a recorded payment raises one alert and an overdue invoice is alerted once', function (): void {
        [$user, $tenant] = createFinanceContext();
        if (! Illuminate\Support\Facades\Schema::hasTable('alert_rules')) {
            $this->markTestSkipped('Alert tables are not available in this environment.');
        }
        financeAlertRule($tenant, $user, App\Services\Alerts\AlertEvent::FINANCE_PAYMENT);
        financeAlertRule($tenant, $user, App\Services\Alerts\AlertEvent::FINANCE_INVOICE_OVERDUE);
        $customer = financeCustomer($tenant);
        $invoice = issueInvoice($tenant, $customer, 100_000);
        $invoice->update(['due_date' => today()->subDays(3)]);

        app(PaymentService::class)->record($tenant->id, $customer, 40_000, today(), [$invoice->id => 40_000]);
        expect($user->notifications()->count())->toBe(1);

        $checked = app(App\Modules\Finance\Services\OverdueInvoiceAlerts::class)->run();
        app(App\Modules\Finance\Services\OverdueInvoiceAlerts::class)->run();
        expect($checked)->toBe(1)->and($user->notifications()->count())->toBe(2);
    });
});

describe('Finance supplier credit notes', function (): void {
    test('a partial credit reduces what is owed, can be undone, and blocks voiding the bill while applied', function (): void {
        [, $tenant] = createFinanceContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $service = app(BillService::class);
        $bank = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::BANK);
        $bill = $service->approve(draftBill($tenant, financeSupplier($tenant), 100_000));

        $credit = $service->createCredit($bill);
        expect($credit->type)->toBe('credit')->and($credit->credit_for_id)->toBe($bill->id)->and($credit->total)->toBe(100_000);

        $account = FinAccount::query()->where('tenant_id', $tenant->id)->where('code', '6200')->firstOrFail();
        $credit = $service->saveDraft($tenant->id, $bill->contact, ['issue_date' => today()->toDateString()], [['description' => 'Faulty month', 'amount' => 30_000, 'account_id' => $account->id]], $credit);
        $service->approve($credit);

        expect($credit->fresh()->number)->toBe('VCN-00001')->and($credit->fresh()->status)->toBe('applied')
            ->and($bill->fresh()->credits_applied)->toBe(30_000)->and($bill->fresh()->balanceDue())->toBe(70_000)
            ->and(accountBalance($tenant, ChartOfAccounts::PAYABLE))->toBe(-70_000)->and(codeBalance($tenant, '6200'))->toBe(70_000)
            ->and(fn () => $service->void($bill->fresh()))->toThrow(DomainException::class, 'credit notes applied')
            ->and(fn () => $service->pay($credit->fresh(), 1, today(), $bank->id))->toThrow(DomainException::class);

        $service->pay($bill->fresh(), 70_000, today(), $bank->id);
        expect($bill->fresh()->status)->toBe('paid');

        $trial = app(FinanceReports::class)->trialBalance($tenant->id);
        expect($trial['debit'])->toBe($trial['credit']);

        $service->voidPayment($bill->payments()->firstOrFail());
        $service->void($credit->fresh());
        expect($bill->fresh()->credits_applied)->toBe(0)->and($bill->fresh()->balanceDue())->toBe(100_000)->and(accountBalance($tenant, ChartOfAccounts::PAYABLE))->toBe(-100_000);
    });

    test('a credit larger than the balance is refused, a paid bill cannot be credited, and credits stay out of aged payables', function (): void {
        [, $tenant] = createFinanceContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $service = app(BillService::class);
        $bank = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::BANK);
        $bill = $service->approve(draftBill($tenant, financeSupplier($tenant), 100_000));
        $service->pay($bill, 80_000, today(), $bank->id);

        $credit = $service->createCredit($bill->fresh());
        expect(fn () => $service->approve($credit))->toThrow(DomainException::class, 'more than the amount still owed');

        $service->pay($bill->fresh(), 20_000, today(), $bank->id);
        expect(fn () => $service->createCredit($bill->fresh()))->toThrow(DomainException::class);

        $other = $service->approve(draftBill($tenant, financeSupplier($tenant, 'Other'), 50_000));
        $service->approve($service->createCredit($other));
        $aged = app(FinanceReports::class)->agedPayables($tenant->id);
        expect($aged['rows'])->toHaveCount(0)->and($aged['totals']['total'])->toBe(0);
    });

    test('the screen drafts a credit note from a bill', function (): void {
        [$user, $tenant] = createFinanceContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $bill = app(BillService::class)->approve(draftBill($tenant, financeSupplier($tenant), 40_000));
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->post(route('finance.bills.credit', $bill->uuid))->assertRedirect();
        $credit = FinBill::query()->where('type', 'credit')->firstOrFail();
        $this->get(route('finance.bills.edit', $credit->uuid))->assertOk()->assertSee('supplier credit note');
        $this->get(route('finance.bills.index'))->assertOk()->assertSee('credit note');
    });
});

describe('Finance split bank matching', function (): void {
    test('one deposit can be matched to several ledger entries that add up to it', function (): void {
        [, $tenant] = createFinanceContext();
        $bank = bankFor($tenant);
        $customer = financeCustomer($tenant);
        $payments = app(PaymentService::class);
        $one = issueInvoice($tenant, $customer, 60_000);
        $two = issueInvoice($tenant, $customer, 40_000);
        $three = issueInvoice($tenant, $customer, 25_000);
        $payments->record($tenant->id, $customer, 60_000, today(), [$one->id => 60_000]);
        $payments->record($tenant->id, $customer, 40_000, today(), [$two->id => 40_000]);
        $payments->record($tenant->id, $customer, 25_000, today(), [$three->id => 25_000]);
        importStatement($bank, "Date,Description,Amount\n".today()->toDateString().",Bulk deposit,1000.00\n");
        $reconciliation = app(BankReconciliation::class);
        $transaction = FinBankTransaction::query()->firstOrFail();
        $lines = $reconciliation->unmatchedLedgerLines($bank)->orderBy('id')->get();

        expect(fn () => $reconciliation->matchMany($transaction, [$lines[0]->id]))->toThrow(DomainException::class, 'at least two')
            ->and(fn () => $reconciliation->matchMany($transaction, [$lines[0]->id, $lines[2]->id]))->toThrow(DomainException::class, 'add up to')
            ->and($reconciliation->suggestions($bank))->toHaveCount(0);

        $reconciliation->matchMany($transaction, [$lines[0]->id, $lines[1]->id]);
        expect($transaction->fresh()->status)->toBe('matched')->and($transaction->fresh()->matchedLines()->count())->toBe(2)
            ->and($reconciliation->unmatchedLedgerLines($bank)->count())->toBe(1)
            ->and(fn () => $reconciliation->matchMany($transaction->fresh(), [$lines[0]->id, $lines[1]->id]))->toThrow(DomainException::class);

        $reconciliation->unmatch($transaction->fresh());
        expect($transaction->fresh()->status)->toBe('unmatched')->and($reconciliation->unmatchedLedgerLines($bank)->count())->toBe(3);

        $reconciliation->matchMany($transaction->fresh(), [$lines[0]->id, $lines[1]->id]);
        $summary = $reconciliation->summary($bank);
        expect($summary['unmatched_statement'])->toBe(0)->and($summary['outstanding_ledger'])->toBe(1);
    });

    test('the screen matches several entries at once', function (): void {
        [$user, $tenant] = createFinanceContext();
        $bank = bankFor($tenant);
        $customer = financeCustomer($tenant);
        $payments = app(PaymentService::class);
        $a = issueInvoice($tenant, $customer, 30_000);
        $b = issueInvoice($tenant, $customer, 20_000);
        $payments->record($tenant->id, $customer, 30_000, today(), [$a->id => 30_000]);
        $payments->record($tenant->id, $customer, 20_000, today(), [$b->id => 20_000]);
        importStatement($bank, "Date,Description,Amount\n".today()->toDateString().",Combined,500.00\n");
        $lines = app(BankReconciliation::class)->unmatchedLedgerLines($bank)->pluck('id')->all();
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->get(route('finance.bank.show', $bank->uuid))->assertOk()->assertSee('Match to several entries');
        $this->post(route('finance.bank.match-many', FinBankTransaction::query()->firstOrFail()->uuid), ['journal_line_ids' => [$lines[0]]])->assertSessionHas('error');
        $this->post(route('finance.bank.match-many', FinBankTransaction::query()->firstOrFail()->uuid), ['journal_line_ids' => $lines])->assertSessionHas('success');
        expect(FinBankTransaction::query()->firstOrFail()->status)->toBe('matched');
        $this->get(route('finance.bank.show', [$bank->uuid, 'show' => 'matched']))->assertOk()->assertSee('2 ledger entries');
    });
});

describe('Finance year-end close', function (): void {
    test('closing a year moves its profit to retained earnings, locks the books and keeps the profit visible in reports', function (): void {
        [$tenant] = statementBooks();
        $reports = app(StatementReports::class);
        $close = app(App\Modules\Finance\Services\YearEndClose::class);
        $yearEnd = $close->suggestedDate($tenant->id);
        $yearStart = $reports->fiscalYearStart($tenant->id, $yearEnd);

        expect($close->preview($tenant->id, $yearEnd)['net_profit'])->toBe(-100_000);

        $journal = $close->close($tenant->id, $yearEnd);
        expect($journal->source_type)->toBe('year_end_close')->and($journal->date->toDateString())->toBe($yearEnd->toDateString())
            ->and($journal->number)->toStartWith('JNL-');

        $retained = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::RETAINED_EARNINGS);
        $row = DB::connection('tenant')->table('fin_journal_lines')->where('account_id', $retained->id)->selectRaw('COALESCE(SUM(debit - credit),0) as net')->first();
        expect((int) $row->net)->toBe(100_000)
            ->and($reports->profitAndLoss($tenant->id, $yearStart, $yearEnd)['net_profit'])->toBe(-100_000)
            ->and(codeBalance($tenant, '6100'))->toBe(0)
            ->and(app(FinanceSettings::class)->for($tenant->id)->lock_date->toDateString())->toBe($yearEnd->toDateString());

        $sheet = $reports->balanceSheet($tenant->id, today());
        $trial = app(FinanceReports::class)->trialBalance($tenant->id);
        expect($sheet['balanced'])->toBeTrue()->and($sheet['current_year_profit'])->toBe(200_000)->and($sheet['prior_years_profit'])->toBe(0)
            ->and($trial['debit'])->toBe($trial['credit'])
            ->and($reports->profitAndLoss($tenant->id, $reports->fiscalYearStart($tenant->id, today()), today())['net_profit'])->toBe(200_000);

        expect(fn () => $close->close($tenant->id, $yearEnd))->toThrow(DomainException::class, 'already been closed')
            ->and(fn () => app(LedgerService::class)->post($tenant->id, $yearEnd, [['account_id' => $retained->id, 'debit' => 1], ['account_id' => $retained->id, 'credit' => 1]]))->toThrow(DomainException::class, 'locked');
    });

    test('only a finished financial year can be closed, on its last day, and an empty year has nothing to close', function (): void {
        [, $tenant] = createFinanceContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $close = app(App\Modules\Finance\Services\YearEndClose::class);

        expect(fn () => $close->close($tenant->id, today()->addDay()))->toThrow(DomainException::class, 'before it ends')
            ->and(fn () => $close->close($tenant->id, today()->startOfYear()->subDays(5)))->toThrow(DomainException::class, 'last day')
            ->and(fn () => $close->close($tenant->id, $close->suggestedDate($tenant->id)))->toThrow(DomainException::class, 'nothing to close');
    });

    test('the settings screen closes a year only after typing CLOSE', function (): void {
        [$user, $tenant] = createFinanceContext();
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);
        $yearEnd = app(App\Modules\Finance\Services\YearEndClose::class)->suggestedDate($tenant->id)->toDateString();

        $this->get(route('finance.settings.edit'))->assertOk()->assertSee('Close a financial year');
        $this->post(route('finance.settings.close-year'), ['year_end' => $yearEnd, 'confirm' => 'yes'])->assertStatus(422);
        $this->post(route('finance.settings.close-year'), ['year_end' => $yearEnd, 'confirm' => 'CLOSE'])->assertSessionHas('error');
    });
});

describe('Finance customer statements', function (): void {
    test('a statement shows charges, credits and payments with a running balance and opening balance', function (): void {
        [, $tenant] = createFinanceContext();
        $customer = financeCustomer($tenant);
        $invoice = issueInvoice($tenant, $customer, 100_000);
        app(PaymentService::class)->record($tenant->id, $customer, 40_000, today(), [$invoice->id => 40_000]);
        app(InvoiceService::class)->send(partialCredit($tenant, $customer, $invoice, 10_000));
        $statements = app(App\Modules\Finance\Services\CustomerStatement::class);

        $report = $statements->build($customer, today()->subMonth(), today());
        expect($report['opening'])->toBe(0)->and($report['closing'])->toBe(50_000)->and($report['lines'])->toHaveCount(3)
            ->and(collect($report['lines'])->pluck('type')->sort()->values()->all())->toBe(['Credit note', 'Invoice', 'Payment'])
            ->and($report['closing'])->toBe($invoice->fresh()->balanceDue());

        $later = $statements->build($customer, today()->addDay(), today()->addMonth());
        expect($later['opening'])->toBe(50_000)->and($later['lines'])->toBe([])->and($later['closing'])->toBe(50_000);
    });

    test('drafts and voided documents are left out, and another customer is never mixed in', function (): void {
        [, $tenant] = createFinanceContext();
        $one = financeCustomer($tenant, 'One', 'one@example.test');
        $two = financeCustomer($tenant, 'Two', 'two@example.test');
        issueInvoice($tenant, $one, 10_000);
        $voided = issueInvoice($tenant, $one, 20_000);
        app(InvoiceService::class)->void($voided);
        issueInvoice($tenant, $two, 99_000);
        app(InvoiceService::class)->saveDraft($tenant->id, $one, ['issue_date' => today()->toDateString()], [['description' => 'Draft', 'quantity' => 1, 'unit_price' => 5_000]]);

        $report = app(App\Modules\Finance\Services\CustomerStatement::class)->build($one, today()->subMonth(), today());
        expect($report['closing'])->toBe(10_000)->and($report['lines'])->toHaveCount(1);
    });

    test('the statement screen opens, downloads as CSV and is linked from the contacts list', function (): void {
        [$user, $tenant] = createFinanceContext();
        $customer = financeCustomer($tenant);
        issueInvoice($tenant, $customer, 100_000);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->get(route('finance.contacts.index'))->assertOk()->assertSee('Statement');
        $this->get(route('finance.contacts.statement', $customer->uuid))->assertOk()->assertSee('Statement of account')->assertSee('Amount due');
        $csv = $this->get(route('finance.contacts.statement', [$customer->uuid, 'format' => 'csv']))->assertOk();
        expect($csv->headers->get('Content-Type'))->toContain('text/csv')->and($csv->streamedContent())->toContain('Statement of account');
    });
});

function vatBooks(): array
{
    [$user, $tenant] = createFinanceContext();
    app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
    [$standard, $zero, $exempt] = app(ChartOfAccounts::class)->activateGhanaVat($tenant->id);
    app(FinanceSettings::class)->for($tenant->id)->forceFill(['tax_enabled' => true])->save();

    return [$user, $tenant, $standard, $zero, $exempt];
}

describe('Finance VAT', function (): void {
    test('registering for VAT adds the Ghana codes and the input tax account, and is safe to repeat', function (): void {
        [, $tenant, $standard, $zero, $exempt] = vatBooks();
        $chart = app(ChartOfAccounts::class);
        $chart->activateGhanaVat($tenant->id);

        expect(FinTax::query()->where('tenant_id', $tenant->id)->count())->toBe(3)
            ->and([$standard->category, $zero->category, $exempt->category])->toBe(['standard', 'zero_rated', 'exempt'])
            ->and($standard->fresh()->rate_basis_points)->toBe(2000)->and($zero->fresh()->rate_basis_points)->toBe(0)
            ->and($chart->inputTaxAccount($tenant->id)->system_key)->toBe('input_tax')
            ->and(FinAccount::query()->where('tenant_id', $tenant->id)->where('code', '1300')->count())->toBe(1);
    });

    test('a bill with tax posts the net to expense, the tax to input VAT and the gross to payables', function (): void {
        [, $tenant, $standard] = vatBooks();
        $service = app(BillService::class);
        $account = FinAccount::query()->where('tenant_id', $tenant->id)->where('code', '6200')->firstOrFail();
        $bank = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::BANK);
        $supplier = financeSupplier($tenant);

        $bill = $service->saveDraft($tenant->id, $supplier, ['issue_date' => today()->toDateString()], [['description' => 'Hosting', 'amount' => 50_000, 'account_id' => $account->id, 'tax_id' => $standard->id]]);
        expect($bill->subtotal)->toBe(50_000)->and($bill->tax_total)->toBe(10_000)->and($bill->total)->toBe(60_000);

        $service->approve($bill);
        expect(codeBalance($tenant, '6200'))->toBe(50_000)->and(codeBalance($tenant, '1300'))->toBe(10_000)->and(accountBalance($tenant, ChartOfAccounts::PAYABLE))->toBe(-60_000);

        $service->pay($bill->fresh(), 60_000, today(), $bank->id);
        expect($bill->fresh()->status)->toBe('paid');

        $credit = $service->createCredit(tap($service->approve($service->saveDraft($tenant->id, $supplier, ['issue_date' => today()->toDateString()], [['description' => 'Other', 'amount' => 20_000, 'account_id' => $account->id, 'tax_id' => $standard->id]])), fn () => null));
        $service->approve($credit);
        expect(codeBalance($tenant, '1300'))->toBe(10_000 + 4_000 - 4_000)->and(codeBalance($tenant, '6200'))->toBe(50_000);

        $trial = app(FinanceReports::class)->trialBalance($tenant->id);
        expect($trial['debit'])->toBe($trial['credit']);
    });

    test('tax stays off for a bill when VAT is not switched on', function (): void {
        [, $tenant] = createFinanceContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $standard = app(ChartOfAccounts::class)->activateGhanaVat($tenant->id)[0];
        $account = FinAccount::query()->where('tenant_id', $tenant->id)->where('code', '6200')->firstOrFail();

        $bill = app(BillService::class)->saveDraft($tenant->id, financeSupplier($tenant), ['issue_date' => today()->toDateString()], [['description' => 'x', 'amount' => 10_000, 'account_id' => $account->id, 'tax_id' => $standard->id]]);
        expect($bill->tax_total)->toBe(0)->and($bill->total)->toBe(10_000);
    });

    test('an expense with tax records the gross paid and the tax recoverable', function (): void {
        [, $tenant, $standard] = vatBooks();
        $account = FinAccount::query()->where('tenant_id', $tenant->id)->where('code', '6300')->firstOrFail();
        $bank = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::BANK);

        $expense = app(ExpenseService::class)->record($tenant->id, 10_000, today(), $account->id, $bank->id, null, ['tax_id' => $standard->id]);
        expect($expense->amount)->toBe(12_000)->and($expense->tax_amount)->toBe(2_000)
            ->and(codeBalance($tenant, '6300'))->toBe(10_000)->and(codeBalance($tenant, '1300'))->toBe(2_000)->and(accountBalance($tenant, ChartOfAccounts::BANK))->toBe(-12_000);

        app(ExpenseService::class)->void($expense);
        expect(codeBalance($tenant, '1300'))->toBe(0)->and(accountBalance($tenant, ChartOfAccounts::BANK))->toBe(0);
    });

    test('zero-rated and exempt sales keep their code so they appear on the VAT summary', function (): void {
        [, $tenant, $standard, $zero, $exempt] = vatBooks();
        $customer = financeCustomer($tenant);

        $invoice = app(InvoiceService::class)->saveDraft($tenant->id, $customer, ['issue_date' => today()->toDateString()], [
            ['description' => 'Standard', 'quantity' => 1, 'unit_price' => 100_000, 'tax_id' => $standard->id],
            ['description' => 'Export', 'quantity' => 1, 'unit_price' => 40_000, 'tax_id' => $zero->id],
            ['description' => 'Medical', 'quantity' => 1, 'unit_price' => 30_000, 'tax_id' => $exempt->id],
        ]);
        expect($invoice->tax_total)->toBe(20_000)->and($invoice->lines->pluck('tax_id')->all())->toBe([$standard->id, $zero->id, $exempt->id]);
    });

    test('the VAT summary splits output and input tax by component, nets credits and reconciles with the ledger', function (): void {
        [, $tenant, $standard, $zero] = vatBooks();
        $customer = financeCustomer($tenant);
        $invoices = app(InvoiceService::class);
        $account = FinAccount::query()->where('tenant_id', $tenant->id)->where('code', '6200')->firstOrFail();
        $bank = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::BANK);

        $invoices->send($invoices->saveDraft($tenant->id, $customer, ['issue_date' => today()->toDateString()], [
            ['description' => 'Standard', 'quantity' => 1, 'unit_price' => 100_000, 'tax_id' => $standard->id],
            ['description' => 'Export', 'quantity' => 1, 'unit_price' => 40_000, 'tax_id' => $zero->id],
        ]));
        $bills = app(BillService::class);
        $bills->approve($bills->saveDraft($tenant->id, financeSupplier($tenant), ['issue_date' => today()->toDateString()], [['description' => 'Hosting', 'amount' => 50_000, 'account_id' => $account->id, 'tax_id' => $standard->id]]));
        app(ExpenseService::class)->record($tenant->id, 10_000, today(), $account->id, $bank->id, null, ['tax_id' => $standard->id]);

        $report = app(App\Modules\Finance\Services\VatReport::class)->summary($tenant->id, today()->startOfYear(), today());

        expect($report['sales'])->toBe(['standard' => 100_000, 'zero_rated' => 40_000, 'exempt' => 0, 'uncoded' => 0])
            ->and($report['purchases']['standard'])->toBe(60_000)
            ->and($report['output']['total'])->toBe(20_000)->and($report['output']['components'])->toBe(['VAT' => 15_000, 'NHIL' => 2_500, 'GETFund' => 2_500])
            ->and($report['input']['total'])->toBe(12_000)->and($report['input']['components'])->toBe(['VAT' => 9_000, 'NHIL' => 1_500, 'GETFund' => 1_500])
            ->and($report['net_payable'])->toBe(8_000)->and($report['reconciled'])->toBeTrue()
            ->and(array_sum($report['input']['components']))->toBe($report['input']['total']);

        app(LedgerService::class)->post($tenant->id, today(), [
            ['account_id' => app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::TAX_PAYABLE)->id, 'credit' => 500],
            ['account_id' => $bank->id, 'debit' => 500],
        ], 'Manual tax adjustment');
        expect(app(App\Modules\Finance\Services\VatReport::class)->summary($tenant->id, today()->startOfYear(), today())['reconciled'])->toBeFalse();
    });

    test('a customer credit note reduces output tax on the summary', function (): void {
        [, $tenant, $standard] = vatBooks();
        $customer = financeCustomer($tenant);
        $invoice = issueInvoice($tenant, $customer, 100_000, 1, ['tax_id' => $standard->id]);
        $service = app(InvoiceService::class);
        $service->send($service->createCreditNote($invoice->fresh()));

        $report = app(App\Modules\Finance\Services\VatReport::class)->summary($tenant->id, today()->startOfYear(), today());
        expect($report['output']['total'])->toBe(0)->and($report['sales']['standard'])->toBe(0)->and($report['reconciled'])->toBeTrue();
    });

    test('the VAT screens: switch on, report, CSV and form fields', function (): void {
        [$user, $tenant] = createFinanceContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->get(route('finance.settings.edit'))->assertOk()->assertSee('Switch on VAT');
        $this->get(route('finance.reports.vat'))->assertOk()->assertSee('VAT is switched off');
        $this->post(route('finance.settings.register-vat'))->assertSessionHas('success');
        expect(app(FinanceSettings::class)->for($tenant->id)->tax_enabled)->toBeTrue();

        $this->get(route('finance.reports.vat'))->assertOk()->assertSee('Output tax')->assertDontSee('VAT is switched off');
        $csv = $this->get(route('finance.reports.vat', ['format' => 'csv']))->assertOk();
        expect($csv->streamedContent())->toContain('VAT summary');
        $this->get(route('finance.bills.create'))->assertOk()->assertSee('No tax');
        $this->get(route('finance.expenses.index'))->assertOk()->assertSee('Add VAT');
    });
});

describe('Finance e-VAT readiness', function (): void {
    test('with e-VAT off nothing changes, and with it on a taxed invoice waits for submission', function (): void {
        [, $tenant, $standard] = vatBooks();
        $customer = financeCustomer($tenant);

        $plain = issueInvoice($tenant, $customer, 100_000, 1, ['tax_id' => $standard->id]);
        expect($plain->fresh()->evat_status)->toBe('not_required');

        app(FinanceSettings::class)->for($tenant->id)->forceFill(['evat_enabled' => true])->save();
        $taxed = issueInvoice($tenant, $customer, 100_000, 1, ['tax_id' => $standard->id]);
        $untaxed = issueInvoice($tenant, $customer, 50_000);

        expect($taxed->fresh()->evat_status)->toBe('pending')->and($taxed->fresh()->evat_message)->toContain('No e-VAT connection')
            ->and($untaxed->fresh()->evat_status)->toBe('not_required');
    });

    test('a connected provider submits and records the reference, a failing one is retried later', function (): void {
        [$user, $tenant, $standard] = vatBooks();
        app(FinanceSettings::class)->for($tenant->id)->forceFill(['evat_enabled' => true])->save();
        $customer = financeCustomer($tenant);
        $state = new ArrayObject(['calls' => 0]);
        app()->bind(App\Modules\Finance\Contracts\EvatProvider::class, fn () => new class($state) implements App\Modules\Finance\Contracts\EvatProvider
        {
            public function __construct(private ArrayObject $state) {}

            public function isConfigured(): bool
            {
                return true;
            }

            public function submit(FinInvoice $invoice): array
            {
                $this->state['calls'] = $this->state['calls'] + 1;

                return $this->state['calls'] === 1 ? ['status' => 'failed', 'reference' => null, 'message' => 'Timeout'] : ['status' => 'submitted', 'reference' => 'GRA-'.$invoice->number, 'message' => null];
            }
        });

        $invoice = issueInvoice($tenant, $customer, 100_000, 1, ['tax_id' => $standard->id]);
        expect($invoice->fresh()->evat_status)->toBe('failed');

        expect(app(App\Modules\Finance\Services\EvatQueue::class)->retryPending($tenant->id))->toBe(1)
            ->and($invoice->fresh()->evat_status)->toBe('submitted')->and($invoice->fresh()->evat_reference)->toBe('GRA-'.$invoice->number)
            ->and($invoice->fresh()->evat_submitted_at)->not->toBeNull();

        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id])->get(route('finance.invoices.show', $invoice->uuid))->assertOk()->assertSee('GRA-'.$invoice->number);
    });

    test('the setting is saved from the settings screen', function (): void {
        [$user, $tenant] = createFinanceContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->put(route('finance.settings.update'), ['base_currency' => 'GHS', 'fiscal_year_start_month' => 1, 'default_payment_terms_days' => 14, 'evat_enabled' => '1'])->assertSessionHas('success');
        expect(app(FinanceSettings::class)->for($tenant->id)->evat_enabled)->toBeTrue();
        $this->get(route('finance.settings.edit'))->assertOk()->assertSee('Prepare invoices for GRA e-VAT');
    });
});

describe('Finance gateway payouts', function (): void {
    test('importing finds new successful payouts once and posting moves clearing to bank and fees to charges', function (): void {
        [, $tenant] = createFinanceContext();
        connectPaystack($tenant);
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $customer = financeCustomer($tenant);
        $invoice = issueInvoice($tenant, $customer, 100_000);
        app(PaymentService::class)->record($tenant->id, $customer, 100_000, today(), [$invoice->id => 100_000], ['gateway' => 'paystack', 'gateway_reference' => 'ref_pay']);
        expect(accountBalance($tenant, ChartOfAccounts::GATEWAY_CLEARING))->toBe(100_000);

        Http::fake(['api.paystack.co/settlement*' => Http::response(['data' => [
            ['id' => 5501, 'total_amount' => 100_000, 'total_fees' => 1_950, 'total_processed' => 98_050, 'settlement_date' => today()->toDateString().'T00:00:00.000Z', 'status' => 'success'],
            ['id' => 5502, 'total_amount' => 40_000, 'total_fees' => 600, 'total_processed' => 39_400, 'settlement_date' => today()->toDateString().'T00:00:00.000Z', 'status' => 'pending'],
        ]])]);
        $service = app(App\Modules\Finance\Services\SettlementService::class);

        expect($service->import($tenant->id))->toBe(1)->and($service->import($tenant->id))->toBe(0);

        $settlement = App\Modules\Finance\Models\FinSettlement::query()->firstOrFail();
        expect($settlement->external_id)->toBe('5501')->and($settlement->net)->toBe(98_050);

        $service->post($settlement);
        expect(accountBalance($tenant, ChartOfAccounts::GATEWAY_CLEARING))->toBe(0)->and(accountBalance($tenant, ChartOfAccounts::BANK))->toBe(98_050)
            ->and(codeBalance($tenant, '6400'))->toBe(1_950)->and($settlement->fresh()->status)->toBe('posted')
            ->and(fn () => $service->post($settlement->fresh()))->toThrow(DomainException::class, 'already in the books');

        $trial = app(FinanceReports::class)->trialBalance($tenant->id);
        expect($trial['debit'])->toBe($trial['credit']);
    });

    test('a payout that does not add up is refused and importing needs a connected gateway', function (): void {
        [, $tenant] = createFinanceContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $service = app(App\Modules\Finance\Services\SettlementService::class);

        expect(fn () => $service->import($tenant->id))->toThrow(DomainException::class, 'not set up');

        $bad = App\Modules\Finance\Models\FinSettlement::query()->create(['tenant_id' => $tenant->id, 'gateway' => 'paystack', 'external_id' => '9', 'settled_on' => today(), 'gross' => 1_000, 'fees' => 100, 'net' => 950, 'status' => 'imported']);
        expect(fn () => $service->post($bad))->toThrow(DomainException::class, 'does not add up');
    });

    test('the payouts screen lists, fetches and records, and needs the bank permission to act', function (): void {
        [$user, $tenant] = createFinanceContext();
        connectPaystack($tenant);
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        Http::fake(['api.paystack.co/settlement*' => Http::response(['data' => [['id' => 7, 'total_amount' => 50_000, 'total_fees' => 750, 'total_processed' => 49_250, 'settlement_date' => today()->toDateString(), 'status' => 'success']]])]);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->get(route('finance.settlements.index'))->assertOk()->assertSee('Fetch payouts');
        $this->post(route('finance.settlements.import'))->assertSessionHas('success');
        $settlement = App\Modules\Finance\Models\FinSettlement::query()->firstOrFail();
        $this->get(route('finance.settlements.index'))->assertOk()->assertSee('Record payout');
        $this->post(route('finance.settlements.post', $settlement->uuid))->assertSessionHas('success');

        $user->revokePermissionTo('finance.bank.manage');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->post(route('finance.settlements.import'))->assertForbidden();
    });
});

describe('Finance attachments', function (): void {
    test('a receipt can be attached to a bill and an expense, downloaded and removed', function (): void {
        Illuminate\Support\Facades\Storage::fake('tenant');
        [$user, $tenant] = createFinanceContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $bill = app(BillService::class)->approve(draftBill($tenant, financeSupplier($tenant), 10_000));
        $account = FinAccount::query()->where('tenant_id', $tenant->id)->where('code', '6300')->firstOrFail();
        $bank = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::BANK);
        $expense = app(ExpenseService::class)->record($tenant->id, 5_000, today(), $account->id, $bank->id);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->post(route('finance.attachments.store', ['bill', $bill->uuid]), ['file' => Illuminate\Http\UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf')])->assertSessionHas('success');
        $this->post(route('finance.attachments.store', ['expense', $expense->uuid]), ['file' => Illuminate\Http\UploadedFile::fake()->image('receipt.jpg')])->assertSessionHas('success');
        expect(App\Modules\Finance\Models\FinAttachment::query()->count())->toBe(2);

        $file = App\Modules\Finance\Models\FinAttachment::query()->where('attachable_type', 'bill')->firstOrFail();
        Illuminate\Support\Facades\Storage::disk('tenant')->assertExists($file->path);
        $this->get(route('finance.bills.show', $bill->uuid))->assertOk()->assertSee('invoice.pdf');
        $this->get(route('finance.expenses.index'))->assertOk()->assertSee('receipt.jpg');
        $this->get(route('finance.attachments.download', $file->uuid))->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->delete(route('finance.attachments.destroy', $file->uuid))->assertSessionHas('success');
        Illuminate\Support\Facades\Storage::disk('tenant')->assertMissing($file->path);
        expect(App\Modules\Finance\Models\FinAttachment::query()->count())->toBe(1);
    });

    test('unsafe or oversized files are refused and attachments stay private to the organisation', function (): void {
        Illuminate\Support\Facades\Storage::fake('tenant');
        [$user, $tenant] = createFinanceContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $bill = app(BillService::class)->approve(draftBill($tenant, financeSupplier($tenant), 10_000));
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);
        $url = route('finance.attachments.store', ['bill', $bill->uuid]);

        $this->post($url, ['file' => Illuminate\Http\UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHas('error');
        $this->post($url, ['file' => Illuminate\Http\UploadedFile::fake()->create('huge.pdf', 6_000, 'application/pdf')])->assertSessionHasErrors('file');
        $this->post(route('finance.attachments.store', ['invoice', $bill->uuid]), ['file' => Illuminate\Http\UploadedFile::fake()->create('a.pdf', 10)])->assertClientError();
        expect(App\Modules\Finance\Models\FinAttachment::query()->count())->toBe(0);

        $foreign = App\Modules\Finance\Models\FinAttachment::query()->create(['tenant_id' => (string) Str::uuid(), 'attachable_type' => 'bill', 'attachable_id' => 1, 'name' => 'x.pdf', 'path' => 'p', 'mime' => 'application/pdf', 'size' => 1]);
        $this->get(route('finance.attachments.download', $foreign->uuid))->assertNotFound();
    });
});

describe('Finance PDF exports', function (): void {
    test('every report and the customer statement can be downloaded as a PDF', function (): void {
        [$tenant] = statementBooks();
        $user = User::query()->latest('id')->first();
        $customer = financeCustomer($tenant);
        issueInvoice($tenant, $customer, 10_000);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        foreach (['profit-and-loss', 'balance-sheet', 'cash-flow', 'funds', 'vat', 'aged-receivables', 'aged-payables', 'trial-balance'] as $report) {
            $response = $this->get(route('finance.reports.'.$report, ['format' => 'pdf']))->assertOk();
            expect($response->headers->get('Content-Type'))->toContain('application/pdf')->and(mb_substr($response->getContent(), 0, 4))->toBe('%PDF');
        }

        $statement = $this->get(route('finance.contacts.statement', [$customer->uuid, 'format' => 'pdf']))->assertOk();
        expect(mb_substr($statement->getContent(), 0, 4))->toBe('%PDF');
        $this->get(route('finance.reports.profit-and-loss'))->assertOk()->assertSee('Download PDF');
    });
});
