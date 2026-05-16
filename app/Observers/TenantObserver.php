<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Tenant;
use App\Services\ModuleManager;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tenants are barely useful until their fundamental modules are wired up.
 * We auto-enable the configured set on the `created` event so freshly seeded
 * or admin-created tenants land with `core` (and any other listed modules)
 * already provisioned — no manual artisan step required.
 *
 * Failures are logged but never rethrown: tenant creation must succeed even
 * if a module fails to enable (e.g. missing dependency), and an operator can
 * recover with `php artisan tenant:backfill-onboarding`.
 */
final class TenantObserver
{
    public function __construct(
        private readonly ModuleManager $moduleManager
    ) {}

    public function created(Tenant $tenant): void
    {
        $fundamental = (array) config('modules.fundamental', []);

        foreach ($fundamental as $slug) {
            if (! $this->moduleManager->exists($slug)) {
                Log::warning('TenantObserver: fundamental module not found', [
                    'tenant_id' => $tenant->id,
                    'module' => $slug,
                ]);

                continue;
            }

            if ($this->moduleManager->isEnabledForTenant($slug, $tenant)) {
                continue;
            }

            try {
                $this->moduleManager->enableForTenant($slug, $tenant);
            } catch (Throwable $e) {
                Log::warning('TenantObserver: failed to enable fundamental module', [
                    'tenant_id' => $tenant->id,
                    'module' => $slug,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
