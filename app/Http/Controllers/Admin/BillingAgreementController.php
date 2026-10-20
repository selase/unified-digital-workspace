<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AssignBillingAccessRequest;
use App\Http\Requests\Tenant\StoreBillingAgreementRequest;
use App\Models\HybridCapacityCounter;
use App\Models\Tenant;
use App\Models\TenantBillingAccess;
use App\Models\TenantBillingAgreement;
use App\Models\User;
use App\Services\Tenancy\BillingAccessService;
use App\Services\Tenancy\BillingAgreementService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class BillingAgreementController extends Controller
{
    public function index(Tenant $tenant, BillingAgreementService $agreements): View
    {
        Gate::authorize('admin.tenants.update');

        return view('admin.tenants.billing-agreements', [
            'tenant' => $tenant,
            'activeAgreement' => $agreements->activeFor($tenant),
            'agreements' => TenantBillingAgreement::query()->where('tenant_id', $tenant->id)->orderByDesc('effective_from')->get(),
            'members' => $tenant->users()->orderBy('first_name')->get(),
            'accessLevels' => TenantBillingAccess::query()->where('tenant_id', $tenant->id)->pluck('level', 'user_id'),
            'hybrid' => config('usage-pricing.hybrid'),
            'currency' => $tenant->billingCurrency(),
            'capacityCounters' => HybridCapacityCounter::query()->where('tenant_id', $tenant->id)->get(),
        ]);
    }

    public function store(StoreBillingAgreementRequest $request, Tenant $tenant, BillingAgreementService $agreements): RedirectResponse
    {
        $data = $request->validated();
        $agreements->schedule($tenant, $data['plan_slug'], $data['package_slugs'] ?? [], $data['currency'], (int) $data['editor_seats'], $request->boolean('setup_required'), CarbonImmutable::parse($data['effective_from']));

        return to_route('tenants.billing-agreements.index', $tenant->uuid)->with('success', 'Billing agreement scheduled. Earlier agreements have been preserved.');
    }

    public function assign(AssignBillingAccessRequest $request, Tenant $tenant, User $user, BillingAccessService $access): RedirectResponse
    {
        abort_unless($tenant->users()->where('users.id', $user->id)->exists(), 404);
        if ($request->validated('level') === 'unassigned') {
            $access->revoke($tenant, $user);
        } else {
            $access->assign($tenant, $user, $request->validated('level'));
        }

        return to_route('tenants.billing-agreements.index', $tenant->uuid)->with('success', 'Billing access updated. Role permissions have not changed.');
    }

    public function audit(Tenant $tenant, \App\Services\Tenancy\HybridCapacityService $capacity, \App\Services\Tenancy\TenantDatabaseManager $databases, \App\Services\Tenancy\TenantContext $context): RedirectResponse
    {
        Gate::authorize('admin.tenants.update');
        abort_unless(TenantBillingAgreement::query()->where('tenant_id', $tenant->id)->exists(), 422, 'Schedule an agreement before auditing its capacity.');
        $original = $context->getTenant();
        try {
            $databases->configure($tenant);
            $capacity->auditStorage($tenant);
        } finally {
            $original ? $databases->configure($original) : $databases->configureShared();
        }

        return to_route('tenants.billing-agreements.index', $tenant->uuid)->with('success', 'Tenant-owned file storage audited. Website uploads can proceed within the agreement limits.');
    }
}
