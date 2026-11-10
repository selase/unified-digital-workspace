<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Database\Seeders;

use App\Models\Tenant;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Memberships\Models\MemMember;
use App\Modules\Memberships\Models\MemRenewal;
use App\Modules\Memberships\Models\MemType;
use App\Modules\Memberships\Services\MembershipService;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Database\Seeder;

/**
 * Three membership types, members who have paid, one who is waiting to pay and one due to renew.
 * Runs once per tenant and never in production. Pick the tenant with DEMO_TENANT.
 */
final class MembershipsDemoSeeder extends Seeder
{
    private const MARKER = 'Demo: Ama Serwaa';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('MembershipsDemoSeeder: refusing to write demo members into production.');

            return;
        }

        $tenant = Tenant::query()->where('slug', (string) config('app.demo_tenant'))->first();
        if (! $tenant instanceof Tenant) {
            $this->command?->warn('MembershipsDemoSeeder: tenant not found, skipping.');

            return;
        }

        app(TenantContext::class)->setTenant($tenant);
        setPermissionsTeamId($tenant->id);
        $databases = app(TenantDatabaseManager::class);
        $tenant->requiresDedicatedDb() ? $databases->configure($tenant) : $databases->configureShared();

        $tenantId = (string) $tenant->id;
        if (MemMember::query()->where('tenant_id', $tenantId)->where('name', self::MARKER)->exists()) {
            $this->command?->info('MembershipsDemoSeeder: demo members already present, skipping.');

            return;
        }

        $service = app(MembershipService::class);
        $individual = MemType::query()->create(['tenant_id' => $tenantId, 'name' => 'Individual', 'description' => 'For one person.', 'fee' => 12000, 'period_months' => 12]);
        $student = MemType::query()->create(['tenant_id' => $tenantId, 'name' => 'Student', 'description' => 'Reduced rate with a student ID.', 'fee' => 4000, 'period_months' => 12]);
        $friend = MemType::query()->create(['tenant_id' => $tenantId, 'name' => 'Friend of the foundation', 'description' => 'Free, for supporters.', 'fee' => 0, 'period_months' => 12]);

        $payments = app(PaymentService::class);
        $pay = function (MemMember $member) use ($payments, $tenantId, $service): void {
            $renewal = MemRenewal::query()->where('member_id', $member->id)->where('status', 'billed')->with('invoice.contact')->firstOrFail();
            $payments->record($tenantId, $renewal->invoice->contact, $renewal->invoice->total, today(), [$renewal->invoice->id => $renewal->invoice->total], ['method' => 'bank_transfer']);
            $service->applyPayments($tenantId);
        };

        foreach ([[self::MARKER, $individual], ['Demo: Kwesi Boateng', $individual], ['Demo: Efua Mensah', $student], ['Demo: Nana Yaw Opoku', $individual]] as $index => [$name, $type]) {
            $pay($service->join($tenantId, $type, $name, ['email' => 'member'.($index + 1).'@example.test']));
        }

        $service->join($tenantId, $friend, 'Demo: Abena Pratt', ['email' => 'member5@example.test']);
        $service->join($tenantId, $individual, 'Demo: Waiting To Pay', ['email' => 'member6@example.test']);

        $renewing = $service->join($tenantId, $student, 'Demo: Due To Renew', ['email' => 'member7@example.test']);
        $pay($renewing);
        $renewing->forceFill(['expires_on' => today()->addDays(14)])->save();

        $this->command?->info('MembershipsDemoSeeder: 3 types and 7 members created.');
    }
}
