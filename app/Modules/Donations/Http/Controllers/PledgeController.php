<?php

declare(strict_types=1);

namespace App\Modules\Donations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Donations\Http\Requests\SavePledgeRequest;
use App\Modules\Donations\Models\DonCampaign;
use App\Modules\Donations\Models\DonDonor;
use App\Modules\Donations\Models\DonPledge;
use App\Modules\Donations\Services\OnlineGivingService;
use App\Modules\Donations\Services\PledgeService;
use App\Models\Tenant;
use App\Modules\Finance\Models\FinFund;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Support\Money;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PledgeController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'donations.view');

        return view('donations::pledges.index', [
            'pledges' => DonPledge::query()->where('tenant_id', $tenantId)->with(['donor', 'campaign', 'fund'])
                ->withSum('donations as received', 'amount')
                ->orderByRaw("case status when 'active' then 0 when 'paused' then 1 else 2 end")->orderBy('next_due_on')->get(),
            'donors' => DonDonor::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(),
            'campaigns' => DonCampaign::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(),
            'funds' => FinFund::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(),
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function store(SavePledgeRequest $request, PledgeService $pledges): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $donor = DonDonor::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->validated('donor_id'));

        try {
            $pledges->create($tenantId, $donor, Money::toMinor($request->validated('amount')), [
                'frequency' => $request->validated('frequency'),
                'starts_on' => $request->validated('starts_on'),
                'ends_on' => $request->validated('ends_on'),
                'campaign_id' => $request->validated('campaign_id') ? (int) $request->validated('campaign_id') : null,
                'fund_id' => $request->validated('fund_id') ? (int) $request->validated('fund_id') : null,
                'notes' => $request->validated('notes'),
            ]);
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('donations.pledges.index')->with('success', 'Pledge recorded.');
    }

    public function receive(Request $request, PledgeService $pledges, string $pledge): RedirectResponse
    {
        $tenantId = $this->allow($request, 'donations.record');
        $model = DonPledge::query()->where('tenant_id', $tenantId)->where('uuid', $pledge)->firstOrFail();

        try {
            $gift = $pledges->receiveInstalment($model, Carbon::parse((string) $request->input('date', today()->toDateString())), ['method' => (string) $request->input('method', 'bank_transfer')], $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Instalment received as '.$gift->receipt_number.'.');
    }

    /** Makes a payment link for the donor's next instalment and shows it so staff can send it. */
    public function link(Request $request, OnlineGivingService $giving, string $pledge): RedirectResponse
    {
        $tenantId = $this->allow($request, 'donations.record');
        $model = DonPledge::query()->where('tenant_id', $tenantId)->where('uuid', $pledge)->with('donor')->firstOrFail();

        if ($model->status !== DonPledge::STATUS_ACTIVE || ! $giving->isEnabled($tenantId) || ! $model->donor->email) {
            return back()->with('error', 'A payment link needs an active pledge, a donor email address and online payments switched on.');
        }

        return back()->with('pledge_link', ['donor' => $model->donor->name, 'url' => $giving->pledgeLink($model, (string) Tenant::query()->whereKey($tenantId)->value('slug'))]);
    }

    public function status(Request $request, PledgeService $pledges, string $pledge): RedirectResponse
    {
        $tenantId = $this->allow($request, 'donations.record');
        $model = DonPledge::query()->where('tenant_id', $tenantId)->where('uuid', $pledge)->firstOrFail();

        try {
            $pledges->setStatus($model, (string) $request->input('status'));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Pledge updated.');
    }
}
