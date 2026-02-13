<?php

declare(strict_types=1);

namespace App\Modules\Forums\Providers;

use App\Modules\Concerns\ModuleServiceProvider;

final class ForumsServiceProvider extends ModuleServiceProvider
{
    public function getModuleSlug(): string
    {
        return 'forums';
    }

    public function getModuleName(): string
    {
        return 'Forums';
    }
}
