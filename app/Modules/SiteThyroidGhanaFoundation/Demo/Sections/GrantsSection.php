<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Demo\Sections;

use App\Models\Tenant;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Services\ChartOfAccounts;
use App\Modules\Finance\Services\ExpenseService;
use App\Modules\Grants\Models\GrtFunder;
use App\Modules\Grants\Models\GrtReport;
use App\Modules\Grants\Services\GrantService;
use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoSection;
use App\Modules\SiteThyroidGhanaFoundation\Demo\RowRemover;
use Carbon\CarbonImmutable;

/** Funders, grants with instalments (one late), reporting deadlines and spending against a grant's own fund. */
final class GrantsSection implements DemoSection
{
    public function label(): string
    {
        return 'Grants';
    }

    public function seed(Tenant $tenant): void
    {
        $tenantId = (string) $tenant->id;
        $accounts = app(ChartOfAccounts::class);
        $accounts->addNgoAccounts($tenantId);

        $grants = app(GrantService::class);
        $medical = GrtFunder::query()->create(['tenant_id' => $tenantId, 'name' => 'Demo: Teaching hospital partnership', 'type' => 'foundation', 'contact_name' => 'Abena Osei', 'email' => 'grants@teachinghospital.test']);
        $health = GrtFunder::query()->create(['tenant_id' => $tenantId, 'name' => 'Demo: Public health agency', 'type' => 'government', 'contact_name' => 'Dr. Kofi Asare']);
        $bank = GrtFunder::query()->create(['tenant_id' => $tenantId, 'name' => 'Demo: Gold Coast Bank CSR', 'type' => 'corporate']);

        $surgery = $grants->create($tenantId, $medical, 15_000_000, ['title' => 'Demo: Subsidised thyroid surgeries, phase 2', 'reference' => 'TH-2026-014', 'starts_on' => today()->subMonths(5)->toDateString(), 'ends_on' => today()->addMonths(7)->toDateString(), 'status' => 'active', 'purpose' => 'Operations at a third of the full cost for patients who cannot afford them.']);
        $camps = $grants->create($tenantId, $health, 8_000_000, ['title' => 'Demo: Community screening camps', 'reference' => 'PHA/SC/2026/08', 'starts_on' => today()->subMonths(2)->toDateString(), 'ends_on' => today()->addMonths(10)->toDateString(), 'status' => 'awarded']);
        $grants->create($tenantId, $bank, 3_000_000, ['title' => 'Demo: Patient education programme', 'starts_on' => today()->addMonths(1)->toDateString(), 'ends_on' => today()->addMonths(13)->toDateString(), 'status' => 'pipeline']);

        $first = $grants->addInstalment($surgery, 7_500_000, today()->subMonths(5));
        $grants->addInstalment($surgery, 5_000_000, today()->subDays(20));
        $grants->addInstalment($surgery, 2_500_000, today()->addMonths(5));
        $grants->receive($first, today()->subMonths(5)->addDays(3), null, 'TH-TRF-1');

        $second = $grants->addInstalment($camps, 4_000_000, today()->subMonths(2));
        $grants->addInstalment($camps, 4_000_000, today()->addMonths(4));
        $grants->receive($second, today()->subMonths(2)->addDays(1), null, 'PHA-PAY-1');

        $expenses = app(ExpenseService::class);
        $bankAccount = $accounts->bySystemKey($tenantId, ChartOfAccounts::BANK);
        $programme = FinAccount::query()->where('tenant_id', $tenantId)->where('code', '5100')->firstOrFail();
        foreach ([[4, 'Surgery subsidy, first three patients', 2_400_000], [3, 'Pre-operative tests', 650_000], [1, 'Patient follow-up clinic', 380_000]] as [$monthsAgo, $text, $amount]) {
            $expenses->record($tenantId, $amount, today()->subMonthsNoOverflow($monthsAgo)->day(12), $programme->id, $bankAccount->id, null, ['description' => $text, 'fund_id' => $surgery->fund_id]);
        }
        $expenses->record($tenantId, 520_000, today()->subDays(18), $programme->id, $bankAccount->id, null, ['description' => 'Screening camp supplies', 'fund_id' => $camps->fund_id]);

        GrtReport::query()->create(['tenant_id' => $tenantId, 'grant_id' => $surgery->id, 'title' => 'Demo: Six-month narrative and financial report', 'due_on' => today()->addDays(12), 'status' => 'pending']);
        GrtReport::query()->create(['tenant_id' => $tenantId, 'grant_id' => $surgery->id, 'title' => 'Demo: Inception report', 'due_on' => today()->subMonths(3), 'status' => 'submitted', 'submitted_on' => today()->subMonths(3)->addDays(2)]);
        GrtReport::query()->create(['tenant_id' => $tenantId, 'grant_id' => $camps->id, 'title' => 'Demo: Quarterly activity return', 'due_on' => today()->subDays(6), 'status' => 'pending']);
    }

    public function remove(Tenant $tenant, CarbonImmutable $since): int
    {
        $remover = new RowRemover;

        return $remover->rows('grt_budget_lines', $tenant, $since) + $remover->rows('grt_reports', $tenant, $since)
            + $remover->rows('grt_instalments', $tenant, $since) + $remover->rows('grt_grants', $tenant, $since) + $remover->rows('grt_funders', $tenant, $since);
    }
}
