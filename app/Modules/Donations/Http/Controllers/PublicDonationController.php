<?php

declare(strict_types=1);

namespace App\Modules\Donations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\Donations\Models\DonCampaign;
use App\Modules\Donations\Models\DonPledge;
use App\Modules\Donations\Services\OnlineGivingService;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Support\ActivateTenant;
use App\Modules\Finance\Support\Money;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/** The organisation's public "Give" page. Open to anyone, so the only things it can do are start and confirm a gift. */
final class PublicDonationController extends Controller
{
    public function show(Request $request, string $tenant, OnlineGivingService $giving, FinanceSettings $settings): View
    {
        $model = $this->tenant($tenant);

        return view('donations::public.give', [
            'tenant' => $model,
            'profile' => $settings->for($model->id),
            'enabled' => $giving->isEnabled($model->id),
            'campaigns' => DonCampaign::query()->where('tenant_id', $model->id)->where('is_active', true)->orderBy('name')->get(),
            'selectedCampaign' => (int) $request->query('campaign'),
        ]);
    }

    public function start(Request $request, string $tenant, OnlineGivingService $giving): RedirectResponse
    {
        $model = $this->tenant($tenant);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'campaign_id' => ['nullable', 'integer'],
            'website' => ['nullable', 'max:0'],
        ], [
            'name.required' => 'Please tell us your name.',
            'email.required' => 'We need your email to send your receipt.',
            'amount.min' => 'The smallest online gift is 1.00.',
        ]);

        try {
            $url = $giving->checkout($model, [
                'name' => $data['name'],
                'email' => $data['email'],
                'amount' => Money::toMinor($data['amount']),
                'campaign_id' => ! empty($data['campaign_id']) ? (int) $data['campaign_id'] : null,
            ], route('donations.public.thanks', ['tenant' => $model->slug]));

            return redirect()->away($url);
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        } catch (Throwable $error) {
            report($error);

            return back()->withInput()->with('error', 'We could not start the payment. Please try again in a moment.');
        }
    }

    /** The donor opens the signed link staff sent them and goes straight to paying their pledge. */
    public function payPledge(string $tenant, string $pledge, OnlineGivingService $giving): RedirectResponse
    {
        $model = $this->tenant($tenant);
        $record = DonPledge::query()->where('tenant_id', $model->id)->where('uuid', $pledge)->firstOrFail();

        try {
            return redirect()->away($giving->pledgeCheckout($record, route('donations.public.thanks', ['tenant' => $model->slug])));
        } catch (DomainException $error) {
            return redirect()->route('donations.public.give', ['tenant' => $model->slug])->with('error', $error->getMessage());
        } catch (Throwable $error) {
            report($error);

            return redirect()->route('donations.public.give', ['tenant' => $model->slug])->with('error', 'We could not start the payment. Please try again in a moment.');
        }
    }

    public function thanks(Request $request, string $tenant, OnlineGivingService $giving, FinanceSettings $settings): View
    {
        $model = $this->tenant($tenant);
        $reference = (string) ($request->query('reference') ?? $request->query('trxref') ?? '');

        try {
            $gift = $reference !== '' ? $giving->recordVerified($model->id, $reference) : null;
        } catch (Throwable $error) {
            report($error);
            $gift = null;
        }

        return view('donations::public.thanks', ['tenant' => $model, 'profile' => $settings->for($model->id), 'gift' => $gift]);
    }

    private function tenant(string $slug): Tenant
    {
        $tenant = Tenant::query()->where('slug', $slug)->firstOrFail();
        abort_unless(app(\App\Services\ModuleManager::class)->isEnabledForTenant('donations', $tenant), 404);
        ActivateTenant::for($tenant);

        return $tenant;
    }
}
