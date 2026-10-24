<?php

declare(strict_types=1);

namespace App\Modules\SiteKunneo\Providers;

use App\Modules\CmsCore\Providers\CmsSiteServiceProvider;

final class SiteKunneoServiceProvider extends CmsSiteServiceProvider
{
    public function getModuleSlug(): string
    {
        return 'site-kunneo';
    }

    public function getModuleName(): string
    {
        return 'SiteKunneo';
    }

    public function getTenantSlug(): string
    {
        return 'kunneo';
    }
}