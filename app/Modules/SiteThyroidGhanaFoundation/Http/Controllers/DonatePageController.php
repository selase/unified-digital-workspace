<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Http\Controllers;

use App\Models\Tenant;
use App\Modules\Donations\Models\DonCampaign;
use App\Modules\Donations\Services\OnlineGivingService;
use App\Modules\Finance\Support\Money;
use App\Modules\SiteThyroidGhanaFoundation\Demo\PublicVisibility;
use App\Modules\SiteThyroidGhanaFoundation\Http\Requests\StartGiftRequest;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Throwable;

final class DonatePageController extends PublicPageController
{
    public function show(OnlineGivingService $giving): View
    {
        $tenant = $this->tenant();
        $donationsOn = $this->moduleOn('donations', $tenant);

        return view('site-thyroid-ghana-foundation::public.pages.donate', [
            'theme' => $this->theme,
            'online' => $donationsOn && $giving->isEnabled($tenant->id),
            'campaigns' => $donationsOn ? $this->campaigns($tenant) : collect(),
        ]);
    }

    public function store(StartGiftRequest $request, OnlineGivingService $giving): RedirectResponse
    {
        $tenant = $this->tenant();
        abort_unless($this->moduleOn('donations', $tenant), 404);

        try {
            $url = $giving->checkout($tenant, [
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'amount' => Money::toMinor($request->validated('amount')),
                'campaign_id' => $this->campaigns($tenant)->firstWhere('id', (int) $request->validated('campaign_id'))?->id,
            ], route('donations.public.thanks', ['tenant' => $tenant->slug]));

            return redirect()->away($url);
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        } catch (Throwable $error) {
            report($error);

            return back()->withInput()->with('error', 'We could not start the payment. Please try again in a moment.');
        }
    }

    /** @return Collection<int, DonCampaign> */
    private function campaigns(Tenant $tenant): Collection
    {
        return DonCampaign::query()->where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('name')->get()
            ->reject(fn (DonCampaign $campaign): bool => PublicVisibility::isDemo($campaign->name))->values();
    }
}
