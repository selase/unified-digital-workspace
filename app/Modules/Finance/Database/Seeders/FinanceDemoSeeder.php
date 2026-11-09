<?php

declare(strict_types=1);

namespace App\Modules\Finance\Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Models\FinFund;
use App\Modules\Finance\Models\FinItem;
use App\Modules\Finance\Services\BillService;
use App\Modules\Finance\Services\ChartOfAccounts;
use App\Modules\Finance\Services\ExpenseService;
use App\Modules\Finance\Services\InvoiceService;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Finance\Services\PaymentService;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Database\Seeder;

/**
 * Sample customers, suppliers, invoices, bills and expenses in every state, so the
 * Finance screens and reports can be tried without typing data in.
 *
 * Posted journals cannot be deleted, so this never runs in production and only runs
 * once per tenant. Pick the tenant with DEMO_TENANT (default: purpledot).
 *
 *   DEMO_TENANT=purpledot php artisan db:seed --class="App\Modules\Finance\Database\Seeders\FinanceDemoSeeder"
 */
final class FinanceDemoSeeder extends Seeder
{
    private const MARKER = 'Demo: Asante & Sons Ltd';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('FinanceDemoSeeder: refusing to write demo entries into production books.');

            return;
        }

        $tenant = Tenant::query()->where('slug', (string) config('app.demo_tenant'))->first();
        if (! $tenant instanceof Tenant) {
            $this->command?->warn('FinanceDemoSeeder: tenant not found, skipping.');

            return;
        }

        app(TenantContext::class)->setTenant($tenant);
        setPermissionsTeamId($tenant->id);
        $databases = app(TenantDatabaseManager::class);
        $tenant->requiresDedicatedDb() ? $databases->configure($tenant) : $databases->configureShared();

        $tenantId = (string) $tenant->id;
        app(ChartOfAccounts::class)->ensureSeeded($tenantId);

        if (FinContact::query()->where('tenant_id', $tenantId)->where('name', self::MARKER)->exists()) {
            $this->command?->info('FinanceDemoSeeder: demo data already present, skipping.');

            return;
        }

        $userId = User::query()->where('email', 'dev@wearepurpledot.com')->value('uuid');
        $userId = $userId ? (string) $userId : null;

        $bank = app(ChartOfAccounts::class)->bySystemKey($tenantId, ChartOfAccounts::BANK);
        $equity = FinAccount::query()->where('tenant_id', $tenantId)->where('code', '3000')->firstOrFail();
        app(LedgerService::class)->post($tenantId, today()->subDays(90), [
            ['account_id' => $bank->id, 'debit' => 5_000_000, 'memo' => 'Demo opening balance'],
            ['account_id' => $equity->id, 'credit' => 5_000_000, 'memo' => 'Demo opening balance'],
        ], 'Demo opening balance', 'manual', null, $userId);

        $this->seedCustomerSide($tenantId, $userId);
        $this->seedSupplierSide($tenantId, $userId);

        $this->command?->info('FinanceDemoSeeder: demo customers, invoices, bills and expenses created.');
    }

    private function seedCustomerSide(string $tenantId, ?string $userId): void
    {
        $invoices = app(InvoiceService::class);
        $payments = app(PaymentService::class);
        $income = FinAccount::query()->where('tenant_id', $tenantId)->where('code', '4000')->firstOrFail();

        FinFund::query()->firstOrCreate(['tenant_id' => $tenantId, 'code' => 'GEN'], ['name' => 'General fund', 'is_restricted' => false]);
        FinFund::query()->firstOrCreate(['tenant_id' => $tenantId, 'code' => 'YTH'], ['name' => 'Youth programme (restricted)', 'is_restricted' => true]);
        FinItem::query()->firstOrCreate(['tenant_id' => $tenantId, 'name' => 'Website build'], ['unit_price' => 1_500_000, 'income_account_id' => $income->id]);
        FinItem::query()->firstOrCreate(['tenant_id' => $tenantId, 'name' => 'Monthly support retainer'], ['unit_price' => 250_000, 'income_account_id' => $income->id]);

        $asante = FinContact::query()->create(['tenant_id' => $tenantId, 'name' => self::MARKER, 'email' => 'accounts@asante.test', 'payment_terms_days' => 14]);
        $volta = FinContact::query()->create(['tenant_id' => $tenantId, 'name' => 'Demo: Volta Clinic', 'email' => 'finance@voltaclinic.test', 'payment_terms_days' => 30]);
        $kofi = FinContact::query()->create(['tenant_id' => $tenantId, 'name' => 'Demo: Kofi Mensah (individual)', 'email' => 'kofi@example.test']);

        $make = function (FinContact $customer, int $daysAgo, array $lines, string $type = 'invoice') use ($invoices, $tenantId, $userId) {
            $draft = $invoices->saveDraft(
                $tenantId,
                $customer,
                ['type' => $type, 'issue_date' => today()->subDays($daysAgo)->toDateString()],
                array_map(fn (array $line): array => $line + ['quantity' => 1], $lines),
                null,
                $userId,
            );

            return $draft;
        };

        $send = fn ($draft) => $invoices->send($draft, $userId);

        // Fully paid, 45 days old.
        $paid = $send($make($asante, 45, [['description' => 'Website build', 'unit_price' => 1_500_000]]));
        $payments->record($tenantId, $asante, 1_500_000, today()->subDays(30), [$paid->id => 1_500_000], ['method' => 'bank_transfer', 'reference' => 'DEMO-TRF-1'], $userId);

        // Partly paid.
        $partly = $send($make($volta, 20, [['description' => 'Monthly support retainer', 'unit_price' => 250_000], ['description' => 'Extra training day', 'unit_price' => 80_000]]));
        $payments->record($tenantId, $volta, 150_000, today()->subDays(5), [$partly->id => 150_000], ['method' => 'mobile_money', 'reference' => 'DEMO-MM-7'], $userId);

        // Overdue (issued 50 days ago on 14-day terms) and not yet due.
        $send($make($asante, 50, [['description' => 'Monthly support retainer', 'unit_price' => 250_000]]));
        $send($make($kofi, 2, [['description' => 'Logo and brand sheet', 'unit_price' => 120_000]]));

        // A draft, and a quote that is still open.
        $make($volta, 0, [['description' => 'Annual maintenance', 'unit_price' => 900_000]]);
        $send($make($kofi, 1, [['description' => 'Mobile app prototype', 'unit_price' => 2_400_000]], 'quote'));
    }

    private function seedSupplierSide(string $tenantId, ?string $userId): void
    {
        $bills = app(BillService::class);
        $expenses = app(ExpenseService::class);
        $accounts = app(ChartOfAccounts::class);
        $bank = $accounts->bySystemKey($tenantId, ChartOfAccounts::BANK);
        $momo = FinAccount::query()->where('tenant_id', $tenantId)->where('code', '1020')->firstOrFail();
        $byCode = fn (string $code): int => FinAccount::query()->where('tenant_id', $tenantId)->where('code', $code)->firstOrFail()->id;
        $youth = FinFund::query()->where('tenant_id', $tenantId)->where('code', 'YTH')->first();

        $host = FinContact::query()->create(['tenant_id' => $tenantId, 'name' => 'Demo: CloudHost Ghana', 'email' => 'billing@cloudhost.test', 'is_customer' => false, 'is_vendor' => true, 'payment_terms_days' => 14]);
        $landlord = FinContact::query()->create(['tenant_id' => $tenantId, 'name' => 'Demo: Ridge Properties', 'is_customer' => false, 'is_vendor' => true, 'payment_terms_days' => 7]);
        $printer = FinContact::query()->create(['tenant_id' => $tenantId, 'name' => 'Demo: QuickPrint Accra', 'phone' => '+233200000000', 'is_customer' => true, 'is_vendor' => true]);

        $draft = fn (FinContact $vendor, int $daysAgo, string $reference, array $lines) => $bills->saveDraft(
            $tenantId,
            $vendor,
            ['issue_date' => today()->subDays($daysAgo)->toDateString(), 'vendor_reference' => $reference],
            $lines,
            null,
            $userId,
        );

        // Paid in full.
        $paid = $bills->approve($draft($host, 40, 'CH-1041', [['description' => 'Cloud hosting, last month', 'amount' => 180_000, 'account_id' => $byCode('6200')]]), $userId);
        $bills->pay($paid, 180_000, today()->subDays(25), $bank->id, ['reference' => 'DEMO-PAY-1'], $userId);

        // Part-paid, and overdue (issued 35 days ago on 7-day terms).
        $rent = $bills->approve($draft($landlord, 35, 'RENT-NOV', [['description' => 'Office rent', 'amount' => 600_000, 'account_id' => $byCode('6100')]]), $userId);
        $bills->pay($rent, 250_000, today()->subDays(10), $bank->id, ['reference' => 'DEMO-PAY-2'], $userId);

        // Approved and not yet due, and a draft.
        $bills->approve($draft($host, 3, 'CH-1102', [['description' => 'Cloud hosting', 'amount' => 195_000, 'account_id' => $byCode('6200')], ['description' => 'Domain renewals', 'amount' => 25_000, 'account_id' => $byCode('6210')]]), $userId);
        $draft($printer, 0, 'QP-88', [['description' => 'Flyers for the youth drive', 'amount' => 45_000, 'account_id' => $byCode('6300'), 'fund_id' => $youth?->id]]);

        // Paid on the spot.
        $expenses->record($tenantId, 12_000, today()->subDays(9), $byCode('6400'), $bank->id, null, ['description' => 'Bank charges'], $userId);
        $expenses->record($tenantId, 35_000, today()->subDays(4), $byCode('6300'), $momo->id, $printer, ['description' => 'Banner printing', 'reference' => 'RCPT-3321', 'fund_id' => $youth?->id], $userId);
    }
}
