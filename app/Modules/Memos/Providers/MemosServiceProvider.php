<?php

declare(strict_types=1);

namespace App\Modules\Memos\Providers;

use App\Modules\Concerns\ModuleServiceProvider;

final class MemosServiceProvider extends ModuleServiceProvider
{
    public function getModuleSlug(): string
    {
        return 'memos';
    }

    public function getModuleName(): string
    {
        return 'Memos';
    }
}
