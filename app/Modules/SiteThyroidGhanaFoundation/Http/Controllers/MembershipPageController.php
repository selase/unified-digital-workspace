<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Http\Controllers;

use App\Models\Tenant;
use App\Modules\Finance\Services\OnlinePaymentService;
use App\Modules\Memberships\Models\MemType;
use App\Modules\Memberships\Services\MembershipService;
use App\Modules\SiteThyroidGhanaFoundation\Demo\PublicVisibility;
use App\Modules\SiteThyroidGhanaFoundation\Http\Requests\JoinMembershipRequest;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class MembershipPageController extends PublicPageController
{
    public function show(Request $request): View
    {
        $tenant = $this->tenant();

        return view('site-thyroid-ghana-foundation::public.pages.membership', [
            'theme' => $this->theme,
            'tiers' => $this->tiers($tenant),
            'selectedType' => $request->integer('type'),
            'portalOn' => $this->moduleOn('memberships', $tenant),
            'tenantSlug' => $tenant->slug,
        ]);
    }

    public function store(JoinMembershipRequest $request, MembershipService $memberships, OnlinePaymentService $online): View|RedirectResponse
    {
        $tenant = $this->tenant();
        $type = $this->tiers($tenant)->firstWhere('id', (int) $request->validated('type_id'));
        abort_unless($type instanceof MemType, 422);

        $paymentUrl = $this->apply($tenant, $type, $request, $memberships, $online);

        return $paymentUrl !== null ? redirect()->away($paymentUrl) : $this->thanks();
    }

    /** Joins the visitor and returns where to pay the first dues, or null when there is nothing to pay online. A repeat application looks the same as a new one, so the form cannot reveal who is a member. */
    private function apply(Tenant $tenant, MemType $type, JoinMembershipRequest $request, MembershipService $memberships, OnlinePaymentService $online): ?string
    {
        try {
            $member = $memberships->join($tenant->id, $type, $request->validated('name'), ['email' => $request->validated('email'), 'phone' => $request->validated('phone')]);
        } catch (DomainException) {
            return null;
        }

        $invoice = $member->renewals()->with('invoice')->latest('id')->first()?->invoice;

        return $invoice !== null && $online->canCollect($invoice) ? $online->paymentUrl($invoice) : null;
    }

    /** @return Collection<int, MemType> */
    private function tiers(Tenant $tenant): Collection
    {
        if (! $this->moduleOn('memberships', $tenant)) {
            return collect();
        }

        return MemType::query()->where('tenant_id', $tenant->id)->where('is_active', true)->where('is_public', true)->orderBy('fee')->get()
            ->reject(fn (MemType $type): bool => PublicVisibility::isDemo($type->name))->values();
    }

    private function thanks(): View
    {
        return view('site-thyroid-ghana-foundation::public.pages.thanks', [
            'theme' => $this->theme,
            'crumb' => 'Membership',
            'heading' => 'Thank you for applying',
            'message' => 'We have your application. If a dues payment is needed we will email you the details, and we will be in touch about your membership.',
        ]);
    }
}
