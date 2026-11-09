<?php

declare(strict_types=1);

namespace App\Modules\Donations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Donations\Http\Requests\SaveCampaignRequest;
use App\Modules\Donations\Models\DonCampaign;
use App\Modules\Donations\Services\DonationReports;
use App\Modules\Finance\Models\FinFund;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Support\Money;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CampaignController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request, DonationReports $reports, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'donations.view');

        return view('donations::campaigns.index', [
            'rows' => $reports->campaigns($tenantId),
            'funds' => FinFund::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(),
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function store(SaveCampaignRequest $request): RedirectResponse
    {
        DonCampaign::query()->create([
            ...$request->safe()->except('goal_amount'),
            'goal_amount' => Money::toMinor($request->validated('goal_amount') ?? 0),
            'tenant_id' => $this->tenantId($request),
        ]);

        return back()->with('success', 'Campaign created.');
    }

    public function toggle(Request $request, string $campaign): RedirectResponse
    {
        $tenantId = $this->allow($request, 'donations.campaigns.manage');
        $model = DonCampaign::query()->where('tenant_id', $tenantId)->where('uuid', $campaign)->firstOrFail();
        $model->update(['is_active' => ! $model->is_active]);

        return back()->with('success', $model->is_active ? 'Campaign reopened.' : 'Campaign closed.');
    }
}
