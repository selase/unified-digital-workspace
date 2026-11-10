<?php

declare(strict_types=1);

namespace App\Modules\Finance\Console;

use App\Models\Tenant;
use App\Models\TenantModule;
use App\Modules\Finance\Contracts\EvatProvider;
use App\Modules\Finance\Services\EvatQueue;
use App\Modules\Finance\Support\ActivateTenant;
use Illuminate\Console\Command;

final class SubmitEvatInvoices extends Command
{
    protected $signature = 'finance:submit-evat';

    protected $description = 'Submit invoices waiting for e-VAT, for tenants that have e-VAT preparation switched on.';

    public function handle(EvatQueue $queue, EvatProvider $provider): int
    {
        if (! $provider->isConfigured()) {
            $this->warn('No e-VAT connection is configured; invoices stay marked as waiting.');

            return self::SUCCESS;
        }

        $accepted = 0;
        $tenantIds = TenantModule::query()->where('module_slug', 'finance')->where('is_enabled', true)->pluck('tenant_id');
        foreach (Tenant::query()->whereIn('id', $tenantIds)->get() as $tenant) {
            ActivateTenant::for($tenant);
            $accepted += $queue->retryPending((string) $tenant->id);
        }

        $this->info("Submitted {$accepted} invoice(s).");

        return self::SUCCESS;
    }
}
