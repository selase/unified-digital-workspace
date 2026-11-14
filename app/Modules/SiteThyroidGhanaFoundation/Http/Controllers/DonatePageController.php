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
use Illuminate\Http\Request;
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
            ], route(str_replace('.store', '.thanks', (string) $request->route()?->getName())));

            return redirect()->away($url);
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        } catch (Throwable $error) {
            report($error);

            return back()->withInput()->with('error', 'We could not start the payment. Please try again in a moment.');
        }
    }

    public function thanks(Request $request, OnlineGivingService $giving): View
    {
        $tenant = $this->tenant();
        abort_unless($this->moduleOn('donations', $tenant), 404);
        $reference = (string) ($request->query('reference') ?? $request->query('trxref') ?? '');

        try {
            $gift = $reference !== '' ? $giving->recordVerified($tenant->id, $reference) : null;
        } catch (Throwable $error) {
            report($error);
            $gift = null;
        }

        return view('site-thyroid-ghana-foundation::public.pages.thanks', [
            'theme' => $this->theme,
            'crumb' => 'Donate',
            'heading' => $gift === null ? 'We are confirming your gift' : 'Thank you for your gift',
            'message' => $gift === null
                ? 'If you completed the payment, it will appear shortly. If you were charged and hear nothing, please contact us.'
                : 'We received your gift of '.Money::format($gift->amount, $gift->currency).'. Your receipt number is '.$gift->receipt_number.'.',
        ]);
    }

    /** @return Collection<int, DonCampaign> */
    private function campaigns(Tenant $tenant): Collection
    {
        return DonCampaign::query()->where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('name')->get()
            ->reject(fn (DonCampaign $campaign): bool => PublicVisibility::isDemo($campaign->name))->values();
    }
}
