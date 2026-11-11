<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Console;

use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Console\Command;

abstract class DemoCommand extends Command
{
    public const TENANT_SLUG = 'thyroid-ghana-foundation';

    protected function demoTenant(): ?Tenant
    {
        if ($this->option('tenant') !== self::TENANT_SLUG) {
            $this->error('This command only runs for '.self::TENANT_SLUG.'.');

            return null;
        }

        if (app()->isProduction() && ! $this->option('confirm')) {
            $this->error('This changes the live '.self::TENANT_SLUG.' tenant. Run it again with --confirm.');

            return null;
        }

        $tenant = Tenant::query()->where('slug', self::TENANT_SLUG)->first();
        if (! $tenant instanceof Tenant) {
            $this->error('Tenant '.self::TENANT_SLUG.' not found.');

            return null;
        }

        app(TenantContext::class)->setTenant($tenant);
        setPermissionsTeamId($tenant->id);
        $databases = app(TenantDatabaseManager::class);
        $tenant->requiresDedicatedDb() ? $databases->configure($tenant) : $databases->configureShared();

        return $tenant;
    }
}
