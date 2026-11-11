<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Demo\Sections;

use App\Models\Tenant;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinBankAccount;
use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Models\FinFund;
use App\Modules\Finance\Models\FinInvoice;
use App\Modules\Finance\Models\FinItem;
use App\Modules\Finance\Services\BankReconciliation;
use App\Modules\Finance\Services\BankStatementImporter;
use App\Modules\Finance\Services\BillService;
use App\Modules\Finance\Services\ChartOfAccounts;
use App\Modules\Finance\Services\ExpenseService;
use App\Modules\Finance\Services\InvoiceService;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Finance\Services\StockService;
use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoSection;
use App\Modules\SiteThyroidGhanaFoundation\Demo\RowRemover;
use Carbon\CarbonImmutable;

/**
 * Customers, suppliers, invoices, bills, expenses, a bank statement and a stock item in every state.
 * Posted journals cannot be deleted through the app, so removal deletes the tenant's demo finance rows directly.
 */
final class FinanceSection implements DemoSection
{
    public function label(): string
    {
        return 'Finance';
    }

    public function seed(Tenant $tenant): void
    {
        $tenantId = (string) $tenant->id;
        $accounts = app(ChartOfAccounts::class);
        $accounts->ensureSeeded($tenantId);
        $accounts->addNgoAccounts($tenantId);

        $bank = $accounts->bySystemKey($tenantId, ChartOfAccounts::BANK);
        $equity = FinAccount::query()->where('tenant_id', $tenantId)->where('code', '3000')->firstOrFail();
        app(LedgerService::class)->post($tenantId, today()->subDays(90), [
            ['account_id' => $bank->id, 'debit' => 5_000_000, 'memo' => 'Demo opening balance'],
            ['account_id' => $equity->id, 'credit' => 5_000_000, 'memo' => 'Demo opening balance'],
        ], 'Demo opening balance', 'manual', null, null);

        $this->seedCustomerSide($tenantId);
        $this->seedSupplierSide($tenantId);
        $this->seedStock($tenantId);
        $this->seedBankStatement($tenantId);
    }

    public function remove(Tenant $tenant, CarbonImmutable $since): int
    {
        $remover = new RowRemover;
        $removed = 0;

        foreach (['fin_attachments', 'fin_settlements', 'fin_bank_transactions', 'fin_bank_accounts', 'fin_time_billing', 'fin_stock_movements'] as $table) {
            if ($table === 'fin_bank_transactions') {
                $removed += $remover->children('fin_bank_matches', 'transaction_id', 'fin_bank_transactions', $tenant, $since);
            }
            $removed += $remover->rows($table, $tenant, $since);
        }

        $removed += $remover->children('fin_budget_lines', 'budget_id', 'fin_budgets', $tenant, $since) + $remover->rows('fin_budgets', $tenant, $since);
        $removed += $remover->children('fin_bill_lines', 'bill_id', 'fin_bills', $tenant, $since);
        $removed += $remover->rows('fin_bill_payments', $tenant, $since) + $remover->rows('fin_bills', $tenant, $since) + $remover->rows('fin_expenses', $tenant, $since);
        $removed += $remover->children('fin_payment_allocations', 'payment_id', 'fin_payments', $tenant, $since) + $remover->rows('fin_payments', $tenant, $since);
        $removed += $remover->children('fin_invoice_lines', 'invoice_id', 'fin_invoices', $tenant, $since) + $remover->rows('fin_invoices', $tenant, $since);
        $removed += $remover->rows('fin_recurring_invoices', $tenant, $since);
        $removed += $remover->children('fin_journal_lines', 'journal_id', 'fin_journals', $tenant, $since) + $remover->rows('fin_journals', $tenant, $since);

        foreach (['fin_items', 'fin_taxes', 'fin_contacts', 'fin_funds'] as $table) {
            $removed += $remover->rows($table, $tenant, $since);
        }

        return $removed;
    }

    private function seedCustomerSide(string $tenantId): void
    {
        $invoices = app(InvoiceService::class);
        $payments = app(PaymentService::class);
        $income = FinAccount::query()->where('tenant_id', $tenantId)->where('code', '4000')->firstOrFail();

        FinFund::query()->firstOrCreate(['tenant_id' => $tenantId, 'code' => 'DGEN'], ['name' => 'Demo: General fund', 'is_restricted' => false]);
        FinFund::query()->firstOrCreate(['tenant_id' => $tenantId, 'code' => 'DSCR'], ['name' => 'Demo: Screening camps (restricted)', 'is_restricted' => true]);
        FinItem::query()->create(['tenant_id' => $tenantId, 'name' => 'Demo: Screening camp sponsorship', 'unit_price' => 1_500_000, 'income_account_id' => $income->id]);
        FinItem::query()->create(['tenant_id' => $tenantId, 'name' => 'Demo: Awareness week partner package', 'unit_price' => 800_000, 'income_account_id' => $income->id]);

        $sponsor = FinContact::query()->create(['tenant_id' => $tenantId, 'name' => 'Demo: Ghana Health Partners Ltd', 'email' => 'accounts@ghanahealthpartners.test', 'payment_terms_days' => 14]);
        $network = FinContact::query()->create(['tenant_id' => $tenantId, 'name' => 'Demo: Accra Wellness Network', 'email' => 'finance@accrawellness.test', 'payment_terms_days' => 30]);
        $donor = FinContact::query()->create(['tenant_id' => $tenantId, 'name' => 'Demo: Dr. Ama Boateng (individual)', 'email' => 'ama.boateng@example.test']);
        $federation = FinContact::query()->create(['tenant_id' => $tenantId, 'name' => 'Demo: Thyroid Federation International', 'email' => 'grants@thyroidfederation.test', 'payment_terms_days' => 30]);

        $make = function (FinContact $customer, int $daysAgo, array $lines, string $type = 'invoice', array $extra = []) use ($invoices, $tenantId) {
            return $invoices->saveDraft(
                $tenantId,
                $customer,
                ['type' => $type, 'issue_date' => today()->subDays($daysAgo)->toDateString()] + $extra,
                array_map(fn (array $line): array => $line + ['quantity' => 1], $lines),
                null,
                null,
            );
        };
        $send = fn (FinInvoice $draft): FinInvoice => $invoices->send($draft, null);

        $paid = $send($make($sponsor, 45, [['description' => 'Screening camp sponsorship', 'unit_price' => 1_500_000]]));
        $payments->record($tenantId, $sponsor, 1_500_000, today()->subDays(30), [$paid->id => 1_500_000], ['method' => 'bank_transfer', 'reference' => 'DEMO-TRF-1'], null);

        $partly = $send($make($network, 20, [['description' => 'Awareness week partner package', 'unit_price' => 800_000], ['description' => 'Branded banners', 'unit_price' => 120_000]]));
        $payments->record($tenantId, $network, 400_000, today()->subDays(5), [$partly->id => 400_000], ['method' => 'mobile_money', 'reference' => 'DEMO-MM-7'], null);

        $send($make($sponsor, 50, [['description' => 'Awareness week partner package', 'unit_price' => 800_000]]));
        $send($make($donor, 2, [['description' => 'Sponsor a patient forum evening', 'unit_price' => 250_000]]));
        $make($network, 0, [['description' => 'Annual partnership renewal', 'unit_price' => 900_000]]);
        $send($make($donor, 1, [['description' => 'Subsidised surgery sponsorship', 'unit_price' => 2_400_000]], 'quote'));
        $send($make($federation, 6, [['description' => 'Programme support grant instalment', 'unit_price' => 100_000]], 'invoice', ['currency' => 'USD', 'exchange_rate' => 15.5]));
    }

    private function seedSupplierSide(string $tenantId): void
    {
        $bills = app(BillService::class);
        $expenses = app(ExpenseService::class);
        $accounts = app(ChartOfAccounts::class);
        $bank = $accounts->bySystemKey($tenantId, ChartOfAccounts::BANK);
        $momo = FinAccount::query()->where('tenant_id', $tenantId)->where('code', '1020')->firstOrFail();
        $byCode = fn (string $code): int => FinAccount::query()->where('tenant_id', $tenantId)->where('code', $code)->firstOrFail()->id;
        $screening = FinFund::query()->where('tenant_id', $tenantId)->where('code', 'DSCR')->first();

        $lab = FinContact::query()->create(['tenant_id' => $tenantId, 'name' => 'Demo: Korle Bu Lab Services', 'email' => 'billing@lab.test', 'is_customer' => false, 'is_vendor' => true, 'payment_terms_days' => 14]);
        $venue = FinContact::query()->create(['tenant_id' => $tenantId, 'name' => 'Demo: Accra Venue Hire', 'is_customer' => false, 'is_vendor' => true, 'payment_terms_days' => 7]);
        $printer = FinContact::query()->create(['tenant_id' => $tenantId, 'name' => 'Demo: QuickPrint Accra', 'phone' => '+233240000000', 'is_customer' => true, 'is_vendor' => true]);

        $draft = fn (FinContact $vendor, int $daysAgo, string $reference, array $lines) => $bills->saveDraft(
            $tenantId,
            $vendor,
            ['issue_date' => today()->subDays($daysAgo)->toDateString(), 'vendor_reference' => $reference],
            $lines,
            null,
            null,
        );

        $paid = $bills->approve($draft($lab, 40, 'LAB-1041', [['description' => 'Thyroid function tests for the screening camp', 'amount' => 180_000, 'account_id' => $byCode('5100'), 'fund_id' => $screening?->id]]), null);
        $bills->pay($paid, 180_000, today()->subDays(25), $bank->id, ['reference' => 'DEMO-PAY-1'], null);

        $hire = $bills->approve($draft($venue, 35, 'HALL-NOV', [['description' => 'Hall hire for the patients forum', 'amount' => 600_000, 'account_id' => $byCode('6100')]]), null);
        $bills->pay($hire, 250_000, today()->subDays(10), $bank->id, ['reference' => 'DEMO-PAY-2'], null);

        $bills->approve($draft($lab, 3, 'LAB-1102', [['description' => 'Test kits', 'amount' => 195_000, 'account_id' => $byCode('5100'), 'fund_id' => $screening?->id], ['description' => 'Sample transport', 'amount' => 25_000, 'account_id' => $byCode('5100')]]), null);
        $draft($printer, 0, 'QP-88', [['description' => 'Awareness leaflets', 'amount' => 45_000, 'account_id' => $byCode('6300')]]);

        $expenses->record($tenantId, 12_000, today()->subDays(9), $byCode('6400'), $bank->id, null, ['description' => 'Bank charges'], null);
        $expenses->record($tenantId, 35_000, today()->subDays(4), $byCode('6300'), $momo->id, $printer, ['description' => 'Banner printing', 'reference' => 'RCPT-3321'], null);
    }

    private function seedStock(string $tenantId): void
    {
        $item = FinItem::query()->create(['tenant_id' => $tenantId, 'name' => 'Demo: Patient information leaflets', 'unit_price' => 200, 'track_stock' => true, 'reorder_level_thousandths' => 100_000]);
        app(StockService::class)->record($item, 'purchase', 500, CarbonImmutable::today()->subDays(14), 120, 'Printed for the screening camp');
    }

    private function seedBankStatement(string $tenantId): void
    {
        $bank = FinBankAccount::query()->create([
            'tenant_id' => $tenantId,
            'name' => 'Demo: Main current account',
            'account_id' => app(ChartOfAccounts::class)->bySystemKey($tenantId, ChartOfAccounts::BANK)->id,
            'institution' => 'Demo Bank',
            'opening_balance' => 5_000_000,
        ]);

        $csv = "Date,Description,Amount,Reference\n"
            .today()->subDays(30)->toDateString().",Transfer from Ghana Health Partners,15000.00,DEMO-TRF-1\n"
            .today()->subDays(25)->toDateString().",Korle Bu Lab Services,-1800.00,DEMO-PAY-1\n"
            .today()->subDays(10)->toDateString().",Accra Venue Hire,-2500.00,DEMO-PAY-2\n"
            .today()->subDays(9)->toDateString().",Monthly bank charges,-120.00,\n"
            .today()->subDays(6)->toDateString().",POS purchase - stationery,-85.00,POS-4471\n"
            .today()->subDays(2)->toDateString().",Interest earned,12.00,\n";

        $path = tempnam(sys_get_temp_dir(), 'demo-stmt');
        file_put_contents($path, $csv);

        try {
            app(BankStatementImporter::class)->import($bank, $path);
        } finally {
            unlink($path);
        }

        app(BankReconciliation::class)->autoMatch($bank);
    }
}
