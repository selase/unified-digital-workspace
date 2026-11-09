<?php

declare(strict_types=1);

namespace App\Modules\Donations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Donations\Models\DonDonation;
use App\Modules\Donations\Services\DonationReports;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Services\StatementReports;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class DonationDashboardController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request, DonationReports $reports, StatementReports $statements, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'donations.view');
        $to = CarbonImmutable::today();
        $from = $statements->fiscalYearStart($tenantId, $to);

        return view('donations::dashboard', [
            'summary' => $reports->summary($tenantId, $from, $to),
            'topDonors' => $reports->topDonors($tenantId, $from, $to),
            'campaigns' => $reports->campaigns($tenantId)->filter(fn (array $row): bool => $row['campaign']->is_active)->take(5),
            'recent' => DonDonation::query()->where('tenant_id', $tenantId)->whereNull('voided_at')->with(['donor', 'campaign'])->latest('date')->latest('id')->limit(6)->get(),
            'from' => $from,
            'to' => $to,
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }
}
