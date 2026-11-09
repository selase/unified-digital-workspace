<?php

declare(strict_types=1);

namespace App\Modules\Volunteers\Database\Seeders;

use App\Models\Tenant;
use App\Modules\Volunteers\Models\VolOpportunity;
use App\Modules\Volunteers\Models\VolVolunteer;
use App\Modules\Volunteers\Services\VolunteerService;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Database\Seeder;

/**
 * A volunteer roster with approved and waiting hours, opportunities with places filling up, and two public
 * sign-ups awaiting approval. Runs once per tenant and never in production. Pick the tenant with DEMO_TENANT.
 */
final class VolunteersDemoSeeder extends Seeder
{
    private const MARKER = 'Demo: Akosua Darko';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('VolunteersDemoSeeder: refusing to write demo volunteers into production.');

            return;
        }

        $tenant = Tenant::query()->where('slug', (string) config('app.demo_tenant'))->first();
        if (! $tenant instanceof Tenant) {
            $this->command?->warn('VolunteersDemoSeeder: tenant not found, skipping.');

            return;
        }

        app(TenantContext::class)->setTenant($tenant);
        setPermissionsTeamId($tenant->id);
        $databases = app(TenantDatabaseManager::class);
        $tenant->requiresDedicatedDb() ? $databases->configure($tenant) : $databases->configureShared();

        $tenantId = (string) $tenant->id;
        if (VolVolunteer::query()->where('tenant_id', $tenantId)->where('name', self::MARKER)->exists()) {
            $this->command?->info('VolunteersDemoSeeder: demo volunteers already present, skipping.');

            return;
        }

        $service = app(VolunteerService::class);
        $people = [];
        foreach ([
            [self::MARKER, 'Teaching, tutoring'], ['Demo: Nii Armah', 'Driving, logistics'], ['Demo: Esi Quartey', 'Nursing, first aid'],
            ['Demo: Kojo Bediako', 'Photography, social media'], ['Demo: Mariam Abdul', 'Cooking, event help'], ['Demo: Yaa Pokua', 'Fundraising'],
            ['Demo: Samuel Tetteh', 'Construction, repairs'], ['Demo: Linda Owusu', 'Translation (Twi, Ewe, English)'],
        ] as $index => [$name, $skills]) {
            $people[] = $service->register($tenantId, $name, ['email' => 'volunteer'.($index + 1).'@example.test', 'skills' => $skills, 'phone' => '+23324'.random_int(1000000, 9999999)], true);
        }
        $service->register($tenantId, 'Demo: Public Sign-up One', ['email' => 'signup1@example.test', 'skills' => 'Willing to help anywhere'], false);
        $service->register($tenantId, 'Demo: Public Sign-up Two', ['email' => 'signup2@example.test', 'skills' => 'Graphic design'], false);

        $clinic = VolOpportunity::query()->create(['tenant_id' => $tenantId, 'title' => 'Mobile clinic day, Ho', 'location' => 'Ho Municipal', 'description' => 'Registration, crowd flow and first aid.', 'starts_on' => today()->addDays(9), 'spots' => 4, 'status' => 'open']);
        $school = VolOpportunity::query()->create(['tenant_id' => $tenantId, 'title' => 'School feeding: serving week', 'location' => 'Adaklu', 'starts_on' => today()->addDays(3), 'spots' => 0, 'status' => 'open']);
        $past = VolOpportunity::query()->create(['tenant_id' => $tenantId, 'title' => 'Borehole commissioning ceremony', 'location' => 'Tafi Atome', 'starts_on' => today()->subDays(20), 'spots' => 6, 'status' => 'closed']);

        foreach ([0, 1, 2, 4] as $i) {
            $service->signUp($clinic, $people[$i]);
        }
        foreach ([0, 3, 4, 5, 7] as $i) {
            $service->signUp($school, $people[$i]);
        }

        $hourPlan = [[0, 6, 4], [0, 12, 3.5], [1, 5, 8], [1, 20, 6], [2, 9, 5], [2, 21, 5], [3, 15, 3], [4, 7, 4.5], [4, 19, 6], [5, 30, 2], [6, 25, 7], [7, 10, 2.5]];
        foreach ($hourPlan as $index => [$who, $daysAgo, $hours]) {
            $entry = $service->logHours($people[$who], today()->subDays($daysAgo), (float) $hours, 'Volunteer shift', $index % 3 === 0 ? $past : null);
            if ($index < 9) {
                $service->review($entry, true, null);
            }
        }

        $this->command?->info('VolunteersDemoSeeder: 10 volunteers, 3 opportunities and hours created.');
    }
}
