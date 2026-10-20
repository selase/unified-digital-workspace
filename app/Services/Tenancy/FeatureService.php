<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\TenantFeature;
use Illuminate\Support\Facades\Cache;

final class FeatureService
{
    public const string FEATURE_CUSTOM_DOMAINS = 'custom-domains';

    public function __construct(private readonly TenantContext $context) {}

    /**
     * Check if a feature is enabled for the current tenant.
     */
    public function enabled(string $key): bool
    {
        $tenantId = $this->context->activeTenantId();

        if (! $tenantId) {
            return false;
        }

        $tenant = $this->context->getTenant() ?? \App\Models\Tenant::query()->find($tenantId);
        $agreement = $tenant ? app(BillingAgreementService::class)->activeFor($tenant) : null;
        if ($agreement && ($key === self::FEATURE_CUSTOM_DOMAINS || $key === 'commerce' || str_starts_with($key, 'module-'))) {
            $agreements = app(BillingAgreementService::class);

            return str_starts_with($key, 'module-')
                ? in_array(mb_substr($key, 7), $agreements->grantedModules($agreement), true)
                : in_array($key, $agreements->grantedFeatures($agreement), true);
        }

        return (bool) Cache::remember("tenant_{$tenantId}_feature_{$key}", now()->addMinutes(10), fn () => TenantFeature::where('tenant_id', $tenantId)
            ->where('feature_key', $key)
            ->where('enabled', true)
            ->exists());
    }

    /**
     * Enable a feature for the current tenant.
     */
    public function enable(string $key): void
    {
        $tenantId = $this->context->activeTenantId();

        if (! $tenantId) {
            return;
        }

        TenantFeature::updateOrCreate(
            ['tenant_id' => $tenantId, 'feature_key' => $key],
            ['enabled' => true]
        );

        Cache::forget("tenant_{$tenantId}_feature_{$key}");
    }

    /**
     * Disable a feature for the current tenant.
     */
    public function disable(string $key): void
    {
        $tenantId = $this->context->activeTenantId();

        if (! $tenantId) {
            return;
        }

        TenantFeature::updateOrCreate(
            ['tenant_id' => $tenantId, 'feature_key' => $key],
            ['enabled' => false]
        );

        Cache::forget("tenant_{$tenantId}_feature_{$key}");
    }
}
