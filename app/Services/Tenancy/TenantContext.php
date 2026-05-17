<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;

final class TenantContext
{
    private ?Tenant $tenant = null;

    private ?string $activeTenantId = null;

    public function setTenant(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->activeTenantId = (string) $tenant->id;

        // Configure the `tenant` DB connection so callers can immediately
        // query BelongsToTenant models without "Unsupported driver []".
        // Production middleware (ResolveTenant, TenantAwareJob, etc.) already
        // does this explicitly after setTenant; doing it here is idempotent
        // and removes the footgun for tests, seeders, and one-off scripts
        // that call setTenant without remembering the second step.
        app(TenantDatabaseManager::class)->configure($tenant);
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
