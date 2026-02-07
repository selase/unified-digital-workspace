<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Providers;

use App\Modules\Concerns\ModuleServiceProvider;

final class DocumentManagementServiceProvider extends ModuleServiceProvider
{
    public function getModuleSlug(): string
    {
        return 'document-management';
    }

    public function getModuleName(): string
    {
        return 'DocumentManagement';
    }
}
