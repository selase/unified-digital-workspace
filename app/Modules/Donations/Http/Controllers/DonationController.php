<?php

declare(strict_types=1);

namespace App\Modules\Donations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Donations\Http\Requests\RecordDonationRequest;
use App\Modules\Donations\Mail\DonationReceiptMail;
use App\Modules\Donations\Models\DonCampaign;
use App\Modules\Donations\Models\DonDonation;
use App\Modules\Donations\Models\DonDonor;
use App\Modules\Donations\Services\DonationReceiptPdf;
use App\Modules\Donations\Services\DonationService;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinFund;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Support\Money;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

final class DonationController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'donations.view');

        return view('donations::donations.index', [
            'donations' => DonDonation::query()->where('tenant_id', $tenantId)->with(['donor', 'campaign', 'fund'])->latest('date')->latest('id')->paginate(25),
            'donors' => DonDonor::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(),
            'campaigns' => DonCampaign::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(),
            'funds' => FinFund::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(),
            'depositAccounts' => FinAccount::query()->where('tenant_id', $tenantId)->where('type', 'asset')->where('is_active', true)->orderBy('code')->get(),
            'preselectedDonor' => $request->query('donor'),
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function store(RecordDonationRequest $request, DonationService $donations): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $donor = DonDonor::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->validated('donor_id'));

        try {
            $donations->record(
                $tenantId,
                $donor,
                Money::toMinor($request->validated('amount')),
                Carbon::parse($request->validated('date')),
                array_filter([
                    'method' => $request->validated('method'),
                    'reference' => $request->validated('reference'),
                    'notes' => $request->validated('notes'),
                    'campaign_id' => $request->validated('campaign_id') ? (int) $request->validated('campaign_id') : null,
                    'fund_id' => $request->validated('fund_id') ? (int) $request->validated('fund_id') : null,
                    'deposit_account_id' => $request->validated('deposit_account_id') ? (int) $request->validated('deposit_account_id') : null,
                ], fn ($value): bool => $value !== null),
                $this->actorId($request),
            );
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('donations.donations.index')->with('success', 'Donation recorded. You can now send the donor a receipt.');
    }

    public function receipt(Request $request, DonationReceiptPdf $pdf, string $donation): Response
    {
        $model = $this->find($this->allow($request, 'donations.view'), $donation);

        return response($pdf->output($model), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf->filename($model).'"',
        ]);
    }

    public function email(Request $request, FinanceSettings $settings, string $donation): RedirectResponse
    {
        $tenantId = $this->allow($request, 'donations.record');
        $model = $this->find($tenantId, $donation)->load('donor');

        if ($model->isVoid()) {
            return back()->with('error', 'A void donation cannot be receipted.');
        }
        if (! $model->donor->email) {
            return back()->with('error', 'This donor has no email address.');
        }

        $profile = $settings->for($tenantId);
        Mail::to($model->donor->email)->send(new DonationReceiptMail($model, $profile->legal_name ?: (string) config('app.name'), $profile->email));
        $model->forceFill(['receipt_sent_at' => now()])->save();

        return back()->with('success', 'Receipt emailed to '.$model->donor->email.'.');
    }

    public function void(Request $request, DonationService $donations, string $donation): RedirectResponse
    {
        $model = $this->find($this->allow($request, 'donations.record'), $donation);

        try {
            $donations->void($model, $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Donation voided and its ledger entry reversed.');
    }

    private function find(string $tenantId, string $uuid): DonDonation
    {
        return DonDonation::query()->where('tenant_id', $tenantId)->where('uuid', $uuid)->firstOrFail();
    }
}
