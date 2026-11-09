<?php

declare(strict_types=1);

namespace App\Modules\Grants\Database\Seeders;

use App\Models\Tenant;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Services\ChartOfAccounts;
use App\Modules\Finance\Services\ExpenseService;
use App\Modules\Grants\Models\GrtFunder;
use App\Modules\Grants\Models\GrtReport;
use App\Modules\Grants\Services\GrantService;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Database\Seeder;

/**
 * Funders, grants with instalments (some received, one late), reporting deadlines and spending against a grant's
 * fund. Posts real ledger entries, so it never runs in production and runs once per tenant. Pick the tenant
 * with DEMO_TENANT.
 */
final class GrantsDemoSeeder extends Seeder
{
    private const MARKER = 'Demo: Wellspring Foundation';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('GrantsDemoSeeder: refusing to write demo grants into production books.');

            return;
        }

        $tenant = Tenant::query()->where('slug', (string) config('app.demo_tenant'))->first();
        if (! $tenant instanceof Tenant) {
            $this->command?->warn('GrantsDemoSeeder: tenant not found, skipping.');

            return;
        }

        app(TenantContext::class)->setTenant($tenant);
        setPermissionsTeamId($tenant->id);
        $databases = app(TenantDatabaseManager::class);
        $tenant->requiresDedicatedDb() ? $databases->configure($tenant) : $databases->configureShared();

        $tenantId = (string) $tenant->id;
        $accounts = app(ChartOfAccounts::class);
        $accounts->addNgoAccounts($tenantId);

        if (GrtFunder::query()->where('tenant_id', $tenantId)->where('name', self::MARKER)->exists()) {
            $this->command?->info('GrantsDemoSeeder: demo grants already present, skipping.');

            return;
        }

        $grants = app(GrantService::class);
        $wellspring = GrtFunder::query()->create(['tenant_id' => $tenantId, 'name' => self::MARKER, 'type' => 'foundation', 'contact_name' => 'Abena Osei', 'email' => 'grants@wellspring.test']);
        $mohs = GrtFunder::query()->create(['tenant_id' => $tenantId, 'name' => 'Demo: Ministry of Health partnership', 'type' => 'government', 'contact_name' => 'Dr. Kofi Asare']);
        $bank = GrtFunder::query()->create(['tenant_id' => $tenantId, 'name' => 'Demo: Gold Coast Bank CSR', 'type' => 'corporate']);

        $clean = $grants->create($tenantId, $wellspring, 18_000_000, ['title' => 'Clean water for 12 villages', 'reference' => 'WSF-2026-014', 'starts_on' => today()->subMonths(5)->toDateString(), 'ends_on' => today()->addMonths(7)->toDateString(), 'status' => 'active', 'purpose' => 'Boreholes, hand pumps and water-committee training.']);
        $health = $grants->create($tenantId, $mohs, 9_000_000, ['title' => 'Maternal health outreach', 'reference' => 'MOH/PP/2026/08', 'starts_on' => today()->subMonths(2)->toDateString(), 'ends_on' => today()->addMonths(10)->toDateString(), 'status' => 'awarded']);
        $grants->create($tenantId, $bank, 4_000_000, ['title' => 'Girls coding club', 'starts_on' => today()->addMonths(1)->toDateString(), 'ends_on' => today()->addMonths(13)->toDateString(), 'status' => 'pipeline']);

        $first = $grants->addInstalment($clean, 9_000_000, today()->subMonths(5));
        $grants->addInstalment($clean, 6_000_000, today()->subDays(20));
        $grants->addInstalment($clean, 3_000_000, today()->addMonths(5));
        $grants->receive($first, today()->subMonths(5)->addDays(3), null, 'WSF-TRF-1');

        $second = $grants->addInstalment($health, 4_500_000, today()->subMonths(2));
        $grants->addInstalment($health, 4_500_000, today()->addMonths(4));
        $grants->receive($second, today()->subMonths(2)->addDays(1), null, 'MOH-PAY-1');

        $expenses = app(ExpenseService::class);
        $bankAccount = $accounts->bySystemKey($tenantId, ChartOfAccounts::BANK);
        $programme = FinAccount::query()->where('tenant_id', $tenantId)->where('code', '5100')->firstOrFail();
        foreach ([[4, 'Borehole drilling, villages 1 to 3', 2_400_000], [3, 'Hand pumps and fittings', 1_150_000], [1, 'Water-committee training', 380_000]] as [$monthsAgo, $text, $amount]) {
            $expenses->record($tenantId, $amount, today()->subMonthsNoOverflow($monthsAgo)->day(12), $programme->id, $bankAccount->id, null, ['description' => $text, 'fund_id' => $clean->fund_id]);
        }
        $expenses->record($tenantId, 520_000, today()->subDays(18), $programme->id, $bankAccount->id, null, ['description' => 'Mobile antenatal clinic supplies', 'fund_id' => $health->fund_id]);

        GrtReport::query()->create(['tenant_id' => $tenantId, 'grant_id' => $clean->id, 'title' => 'Six-month narrative and financial report', 'due_on' => today()->addDays(12), 'status' => 'pending']);
        GrtReport::query()->create(['tenant_id' => $tenantId, 'grant_id' => $clean->id, 'title' => 'Inception report', 'due_on' => today()->subMonths(3), 'status' => 'submitted', 'submitted_on' => today()->subMonths(3)->addDays(2)]);
        GrtReport::query()->create(['tenant_id' => $tenantId, 'grant_id' => $health->id, 'title' => 'Quarterly activity return', 'due_on' => today()->subDays(6), 'status' => 'pending']);

        $this->command?->info('GrantsDemoSeeder: 3 funders, 3 grants, instalments, reports and spending created.');
    }
}
