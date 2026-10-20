<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enum\UsageMetric;
use App\Models\Tenant;
use App\Services\Tenancy\StorageMeter;
use App\Services\Tenancy\UsageService;
use Illuminate\Console\Command;

final class AuditTenantStorage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:audit-storage {--tenant= : Audit only this tenant UUID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit storage usage for all tenants and record metrics';

    /**
     * Execute the console command.
     */
    public function handle(StorageMeter $meter, UsageService $usageService): void
    {
        $tenants = Tenant::query()->when($this->option('tenant'), fn ($query, $id) => $query->whereKey($id))->get();

        $this->info("Starting storage audit for {$tenants->count()} tenants...");

        foreach ($tenants as $tenant) {
            $this->comment("Auditing Tenant: {$tenant->name} ({$tenant->id})");

            $capacity = app(\App\Services\Tenancy\HybridCapacityService::class);
            $bytes = $capacity->limit($tenant, 'files') !== null
                ? $capacity->auditStorage($tenant)
                : $meter->calculateUsage($tenant);

            $usageService->recordDailyGauge(
                $tenant,
                UsageMetric::STORAGE_BYTES,
                $bytes
            );

            $this->line(' - Usage: '.number_format($bytes / 1024 / 1024, 2).' MB');
        }

        $this->info('Storage audit completed.');
    }
}
