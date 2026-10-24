<?php

declare(strict_types=1);

namespace App\Modules\SiteReadysetgo\Providers;

use App\Modules\CmsCore\Providers\CmsSiteServiceProvider;

final class SiteReadysetgoServiceProvider extends CmsSiteServiceProvider
{
    public function getModuleSlug(): string
    {
        return 'site-readysetgo';
    }

    public function getModuleName(): string
    {
        return 'SiteReadysetgo';
    }

    public function getTenantSlug(): string
    {
        return 'readysetgo';
    }
}