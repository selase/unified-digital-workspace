<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Providers;

use App\Modules\Concerns\ModuleServiceProvider;

final class QualityMonitoringServiceProvider extends ModuleServiceProvider
{
    public function getModuleSlug(): string
    {
        return 'quality-monitoring';
    }

    public function getModuleName(): string
    {
        return 'QualityMonitoring';
    }
}
