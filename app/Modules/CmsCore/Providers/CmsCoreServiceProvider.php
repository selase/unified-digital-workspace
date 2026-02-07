<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Providers;

use App\Modules\Concerns\ModuleServiceProvider;

final class CmsCoreServiceProvider extends ModuleServiceProvider
{
    /**
     * Get the module slug.
     */
    public function getModuleSlug(): string
    {
        return 'cms-core';
    }

    /**
     * Get the module name (directory name).
     */
    public function getModuleName(): string
    {
        return 'CmsCore';
    }
}
