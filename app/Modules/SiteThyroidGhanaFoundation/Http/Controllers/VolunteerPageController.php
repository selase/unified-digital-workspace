<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Http\Controllers;

use App\Models\Tenant;
use App\Modules\SiteThyroidGhanaFoundation\Demo\PublicVisibility;
use App\Modules\SiteThyroidGhanaFoundation\Http\Requests\VolunteerSignupRequest;
use App\Modules\Volunteers\Models\VolOpportunity;
use App\Modules\Volunteers\Services\VolunteerService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

final class VolunteerPageController extends PublicPageController
{
    public function show(): View
    {
        $tenant = $this->tenant();
        $on = $this->moduleOn('volunteers', $tenant);

        return view('site-thyroid-ghana-foundation::public.pages.volunteer', [
            'theme' => $this->theme,
            'moduleOn' => $on,
            'opportunities' => $on ? $this->opportunities($tenant) : collect(),
            'tenantSlug' => $tenant->slug,
        ]);
    }

    public function store(VolunteerSignupRequest $request, VolunteerService $volunteers): View
    {
        $tenant = $this->tenant();
        abort_unless($this->moduleOn('volunteers', $tenant), 404);

        try {
            $volunteers->register($tenant->id, $request->validated('name'), ['email' => $request->validated('email'), 'phone' => $request->validated('phone'), 'skills' => $request->validated('skills')], false);
        } catch (DomainException) {
            return $this->thanks();
        }

        return $this->thanks();
    }

    /** @return Collection<int, VolOpportunity> */
    private function opportunities(Tenant $tenant): Collection
    {
        return VolOpportunity::query()->where('tenant_id', $tenant->id)->where('status', 'open')->whereDate('starts_on', '>=', today())->orderBy('starts_on')->get()
            ->reject(fn (VolOpportunity $opportunity): bool => PublicVisibility::isDemo($opportunity->title))->values();
    }

    private function thanks(): View
    {
        return view('site-thyroid-ghana-foundation::public.pages.thanks', [
            'theme' => $this->theme,
            'crumb' => 'Volunteer',
            'heading' => 'Thank you for offering your time',
            'message' => 'We have your details. A member of the team will review your sign-up and get in touch.',
        ]);
    }
}
