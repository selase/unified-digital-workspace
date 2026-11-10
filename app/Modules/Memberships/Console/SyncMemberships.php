<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Console;

use App\Models\Tenant;
use App\Models\TenantModule;
use App\Modules\Finance\Support\ActivateTenant;
use App\Modules\Memberships\Services\MembershipService;
use Illuminate\Console\Command;
use Throwable;

final class SyncMemberships extends Command
{
    protected $signature = 'memberships:sync';

    protected $description = 'Apply paid dues, expire lapsed memberships and invoice upcoming renewals for every organisation using Memberships.';

    public function handle(MembershipService $memberships): int
    {
        $tenantIds = TenantModule::query()->where('module_slug', 'memberships')->where('is_enabled', true)->pluck('tenant_id');

        foreach (Tenant::query()->whereIn('id', $tenantIds)->get() as $tenant) {
            try {
                ActivateTenant::for($tenant);
                $paid = $memberships->applyPayments($tenant->id);
                $expired = $memberships->expireLapsed($tenant->id);
                $invoiced = $memberships->invoiceUpcomingRenewals($tenant);
                $this->info($tenant->slug.": {$paid} paid, {$expired} expired, {$invoiced} renewal(s) invoiced.");
            } catch (Throwable $error) {
                report($error);
                $this->error($tenant->slug.': '.$error->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
