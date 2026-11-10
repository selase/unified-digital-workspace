<?php

declare(strict_types=1);

namespace App\Modules\MonitoringEvaluation\Providers;

use App\Modules\Concerns\ModuleServiceProvider;

final class MonitoringEvaluationServiceProvider extends ModuleServiceProvider
{
    public function getModuleSlug(): string
    {
        return 'monitoring-evaluation';
    }

    public function getModuleName(): string
    {
        return 'MonitoringEvaluation';
    }

    protected function bootModule(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
