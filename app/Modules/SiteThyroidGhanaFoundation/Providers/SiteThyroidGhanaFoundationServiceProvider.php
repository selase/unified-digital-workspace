<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Providers;

use App\Modules\CmsCore\Providers\CmsSiteServiceProvider;
use App\Modules\SiteThyroidGhanaFoundation\Console\RemoveDemo;
use App\Modules\SiteThyroidGhanaFoundation\Console\SeedDemo;

final class SiteThyroidGhanaFoundationServiceProvider extends CmsSiteServiceProvider
{
    public function getModuleSlug(): string
    {
        return 'site-thyroid-ghana-foundation';
    }

    public function getModuleName(): string
    {
        return 'SiteThyroidGhanaFoundation';
    }

    public function getTenantSlug(): string
    {
        return 'thyroid-ghana-foundation';
    }

    protected function registerCommands(): void
    {
        $this->commands([SeedDemo::class, RemoveDemo::class]);
    }
}
