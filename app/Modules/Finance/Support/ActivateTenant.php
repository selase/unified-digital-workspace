<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;

/**
 * Points the request at one tenant's data. Public pages and webhooks have no logged-in
 * tenant, and the tenant scope does nothing without a context, so this must run first.
 */
final class ActivateTenant
{
    public static function for(Tenant $tenant): void
    {
        app(TenantContext::class)->setTenant($tenant);
        setPermissionsTeamId($tenant->id);

        $database = app(TenantDatabaseManager::class);
        if ($tenant->requiresDedicatedDb()) {
            $database->configure($tenant);
        } else {
            $database->configureShared();
        }
    }
}
