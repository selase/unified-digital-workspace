<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Providers;

use App\Modules\CmsCore\Providers\CmsSiteServiceProvider;

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
}