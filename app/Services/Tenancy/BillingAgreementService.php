<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\TenantBillingAccess;
use App\Models\TenantBillingAgreement;
use App\Models\UsageEvent;
use App\Models\UsageRollup;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class BillingAgreementService
{
    public function activeFor(Tenant $tenant, ?CarbonInterface $at = null): ?TenantBillingAgreement
    {
        return TenantBillingAgreement::query()->where('tenant_id', $tenant->id)
            ->whereDate('effective_from', '<=', ($at ?? now())->toDateString())
            ->orderByDesc('effective_from')->first();
    }

    /** @param array<int, string> $packageSlugs */
    public function schedule(Tenant $tenant, string $planSlug, array $packageSlugs, string $currency, int $editorSeats, bool $setupRequired, CarbonInterface $effectiveFrom): TenantBillingAgreement
    {
        Validator::make(compact('planSlug', 'packageSlugs', 'currency', 'editorSeats'), [
            'planSlug' => ['required', Rule::in(array_keys(config('usage-pricing.hybrid.website_plans')))],
            'packageSlugs' => ['present', 'array', 'max:16'],
            'packageSlugs.*' => ['required', 'string', 'distinct', Rule::in(array_keys(config('usage-pricing.hybrid.packages')))],
            'currency' => ['required', Rule::in(['GHS', 'USD'])],
            'editorSeats' => ['required', 'integer', 'min:0', 'max:100000'],
        ])->validate();
        $date = $effectiveFrom->copy()->startOfDay();
        if ($date->day !== 1 || $date->lt(today())) {
            throw ValidationException::withMessages(['effective_from' => 'Agreements start on the first day of a current or future billing month; past dates are not allowed.']);
        }
        if ($currency !== $tenant->billingCurrency()) {
            throw ValidationException::withMessages(['currency' => 'The agreement must use the existing account billing currency.']);
        }
        $minimum = (int) config('usage-pricing.hybrid.website_plans.'.$planSlug.'.editors');
        if ($editorSeats < $minimum) {
            throw ValidationException::withMessages(['editor_seats' => "This plan includes {$minimum} editors; purchased capacity cannot be lower."]);
        }

        return DB::connection('landlord')->transaction(function () use ($tenant, $date, $planSlug, $packageSlugs, $currency, $editorSeats, $setupRequired): TenantBillingAgreement {
            Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            foreach ($tenant->subscriptions()->get() as $subscription) {
                $terminated = in_array($subscription->provider_status, ['canceled', 'cancelled', 'expired', 'incomplete_expired'], true);
                if (! $terminated || $subscription->ends_at?->gt($date) || $subscription->current_period_end?->gt($date)) {
                    throw ValidationException::withMessages(['effective_from' => 'Reconcile and terminate existing provider subscriptions, including their paid-through dates, before adopting hybrid billing.']);
                }
            }
            if (TenantBillingAgreement::query()->where('tenant_id', $tenant->id)->whereDate('effective_from', $date)->exists()) {
                throw ValidationException::withMessages(['effective_from' => 'An agreement already exists for this billing month.']);
            }
            if (TenantBillingAgreement::query()->where('tenant_id', $tenant->id)->where('effective_from', '>', $date)->exists()) {
                throw ValidationException::withMessages(['effective_from' => 'Schedule new agreements after the latest saved agreement; earlier agreements are not rewritten.']);
            }
            if ($date->isToday() && (
                UsageRollup::query()->where('tenant_id', $tenant->id)->where('period_start', '>=', $date)->where('value', '>', 0)->exists()
                || UsageEvent::query()->where('tenant_id', $tenant->id)->where('occurred_at', '>=', $date)->where('quantity', '>', 0)->exists()
                || Invoice::query()->where('tenant_id', $tenant->id)->where('period_start', '<=', $date)->where('period_end', '>=', $date)->exists()
            )) {
                throw ValidationException::withMessages(['effective_from' => 'Usage or an invoice already exists this month. Schedule the transition for a future month.']);
            }
            if (TenantBillingAccess::query()->where('tenant_id', $tenant->id)->where('level', 'website_editor')
                ->whereHas('user.tenants', fn ($query) => $query->where('tenants.id', $tenant->id))->count() > $editorSeats) {
                throw ValidationException::withMessages(['editor_seats' => 'Remove excess website editor assignments before reducing purchased capacity.']);
            }
            if ($setupRequired && TenantBillingAgreement::query()->where('tenant_id', $tenant->id)->exists()) {
                throw ValidationException::withMessages(['setup_required' => 'Standard setup is charged only on the first hybrid agreement.']);
            }

            return TenantBillingAgreement::query()->create([
                'tenant_id' => $tenant->id, 'effective_from' => $date,
                'plan_slug' => $planSlug, 'currency' => $currency,
                'editor_seats' => $editorSeats, 'setup_required' => $setupRequired,
                'snapshot' => [
                    'hybrid' => config('usage-pricing.hybrid'), 'meters' => config('usage-pricing.meters'),
                    'selected_packages' => array_values($packageSlugs), 'dedicated_db' => $tenant->requiresDedicatedDb(),
                ],
            ]);
        });
    }

    /** @return list<string> */
    public function grantedModules(TenantBillingAgreement $agreement): array
    {
        $snapshot = $agreement->snapshot;
        $modules = $snapshot['hybrid']['included_modules'];
        foreach ($snapshot['selected_packages'] as $slug) {
            $modules = [...$modules, ...$snapshot['hybrid']['packages'][$slug]['modules']];
        }

        return array_values(array_unique($modules));
    }

    /** @return list<string> */
    public function grantedFeatures(TenantBillingAgreement $agreement): array
    {
        $snapshot = $agreement->snapshot;
        $features = ['custom-domains'];
        foreach ($snapshot['selected_packages'] as $slug) {
            $features = [...$features, ...$snapshot['hybrid']['packages'][$slug]['features']];
        }

        return array_values(array_unique($features));
    }
}
