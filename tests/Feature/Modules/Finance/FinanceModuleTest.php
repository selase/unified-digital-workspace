<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantPaymentGateway;
use App\Models\User;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Models\FinFund;
use App\Modules\Finance\Models\FinInvoice;
use App\Modules\Finance\Models\FinJournal;
use App\Modules\Finance\Models\FinPayment;
use App\Modules\Finance\Models\FinRecurringInvoice;
use App\Modules\Finance\Models\FinTax;
use App\Modules\Finance\Services\ChartOfAccounts;
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
