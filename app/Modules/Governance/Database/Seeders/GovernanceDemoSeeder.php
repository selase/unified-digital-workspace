<?php

declare(strict_types=1);

namespace App\Modules\Governance\Database\Seeders;

use App\Models\Tenant;
use App\Modules\Governance\Models\GovMember;
use App\Modules\Governance\Services\GovernanceService;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Database\Seeder;

/** A board, a held meeting with approved minutes and resolutions, a draft one, an upcoming one, and actions. Never runs in production. */
final class GovernanceDemoSeeder extends Seeder
{
    private const MARKER = 'Demo: Dr. Kofi Mensah';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('GovernanceDemoSeeder: refusing to write demo data into production.');

            return;
        }

        $tenant = Tenant::query()->where('slug', (string) config('app.demo_tenant'))->first();
        if (! $tenant instanceof Tenant) {
            $this->command?->warn('GovernanceDemoSeeder: tenant not found, skipping.');

            return;
        }

        app(TenantContext::class)->setTenant($tenant);
        setPermissionsTeamId($tenant->id);
        $databases = app(TenantDatabaseManager::class);
        $tenant->requiresDedicatedDb() ? $databases->configure($tenant) : $databases->configureShared();

        $tenantId = (string) $tenant->id;
        if (GovMember::query()->where('tenant_id', $tenantId)->where('name', self::MARKER)->exists()) {
            $this->command?->info('GovernanceDemoSeeder: demo board already present, skipping.');

            return;
        }

        $service = app(GovernanceService::class);
        $members = [];
        foreach ([[self::MARKER, 'Chair', 400], ['Demo: Abena Owusu', 'Treasurer', 60], ['Demo: Yaw Boateng', 'Secretary', 700], ['Demo: Efua Quartey', 'Member', 900], ['Demo: Nii Armah', 'Member', 45]] as [$name, $position, $termEndsInDays]) {
            $members[] = $service->addMember($tenantId, $name, ['position' => $position, 'term_starts_on' => today()->subYears(2)->toDateString(), 'term_ends_on' => today()->addDays($termEndsInDays)->toDateString(), 'email' => str($name)->after('Demo: ')->slug('.').'@example.test']);
        }

        $held = $service->schedule($tenantId, 'Demo: Q3 board meeting', 'Board', today()->subDays(40)->setTime(10, 0), 'Ho, boardroom', 3, "1. Welcome and apologies\n2. Minutes of the last meeting\n3. Finance report\n4. Programme update\n5. Any other business");
        $service->recordAttendance($held, [$members[0]->id => 'present', $members[1]->id => 'present', $members[2]->id => 'present', $members[3]->id => 'apologies', $members[4]->id => 'absent']);
        $service->markHeld($held);
        $service->saveMinutes($held, "The Chair opened the meeting at 10:05.\nThe Treasurer presented the quarter's accounts; income was ahead of budget.\nThe board discussed the borehole programme and agreed to extend it to two more villages.");
        $service->approveMinutes($held, today()->subDays(10));
        $service->addResolution($held, 'The board approved the Q2 accounts as presented.', 'passed');
        $service->addResolution($held, 'The board approved extending the borehole programme to Adaklu and Tafi.', 'passed');
        $service->addAction($tenantId, 'Prepare a funding plan for two more boreholes', $held, $members[1], null, today()->addDays(14));
        $service->addAction($tenantId, 'Send the approved accounts to the auditor', $held, $members[2], null, today()->subDays(5));

        $draft = $service->schedule($tenantId, 'Demo: Finance committee', 'Finance committee', today()->subDays(3)->setTime(14, 0), 'Online', 2, "1. Budget review\n2. Audit timetable");
        $service->recordAttendance($draft, [$members[1]->id => 'present', $members[2]->id => 'present']);
        $service->markHeld($draft);

        $service->schedule($tenantId, 'Demo: Annual general meeting', 'AGM', today()->addDays(30)->setTime(10, 0), 'Ho, community hall', 5, "1. Annual report\n2. Elections\n3. Auditor appointment");

        $this->command?->info('GovernanceDemoSeeder: 5 members, 3 meetings, resolutions and actions created.');
    }
}
