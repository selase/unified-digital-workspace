<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;

/**
 * Intentionally not declared final. Several tests
 * (LlmTopupTest, LlmQuotaTest, LlmRateLimitingTest, TwoFactorTest)
 * use Mockery::mock(TenantContext::class) which can't proxy a final
 * class. Pint's final_class rule skips this file via pint.json.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    private ?string $activeTenantId = null;

    public function setTenant(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->activeTenantId = (string) $tenant->id;
    }

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function setActiveTenantId(?string $tenantId): void
    {
        $this->activeTenantId = $tenantId;
    }

    public function activeTenantId(): ?string
    {
        return $this->activeTenantId;
    }
}
