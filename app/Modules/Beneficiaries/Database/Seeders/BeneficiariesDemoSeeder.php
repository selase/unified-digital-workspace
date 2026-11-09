<?php

declare(strict_types=1);

namespace App\Modules\Beneficiaries\Database\Seeders;

use App\Models\Tenant;
use App\Modules\Beneficiaries\Models\BenBeneficiary;
use App\Modules\Beneficiaries\Services\BeneficiaryService;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Database\Seeder;

/**
 * A small beneficiary register across three programmes, with consent recorded for most people, services,
 * a few cases with notes, and one person who has no consent yet. Runs once per tenant, never in production.
 * Pick the tenant with DEMO_TENANT.
 */
final class BeneficiariesDemoSeeder extends Seeder
{
    private const MARKER = 'Demo: Abigail Kuffour';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('BeneficiariesDemoSeeder: refusing to write demo beneficiaries into production.');

            return;
        }

        $tenant = Tenant::query()->where('slug', (string) config('app.demo_tenant'))->first();
        if (! $tenant instanceof Tenant) {
            $this->command?->warn('BeneficiariesDemoSeeder: tenant not found, skipping.');

            return;
        }

        app(TenantContext::class)->setTenant($tenant);
        setPermissionsTeamId($tenant->id);
        $databases = app(TenantDatabaseManager::class);
        $tenant->requiresDedicatedDb() ? $databases->configure($tenant) : $databases->configureShared();

        $tenantId = (string) $tenant->id;
        if (BenBeneficiary::query()->where('tenant_id', $tenantId)->where('full_name', self::MARKER)->exists()) {
            $this->command?->info('BeneficiariesDemoSeeder: demo beneficiaries already present, skipping.');

            return;
        }

        $service = app(BeneficiaryService::class);
        $people = [
            [self::MARKER, 'female', 11, 'School feeding', 'Adaklu', 'Volta'], ['Demo: Kwesi Ampofo', 'male', 9, 'School feeding', 'Adaklu', 'Volta'],
            ['Demo: Mavis Tsikata', 'female', 12, 'School feeding', 'Tafi Atome', 'Volta'], ['Demo: Daniel Gyamfi', 'male', 10, 'School feeding', 'Ho', 'Volta'],
            ['Demo: Rebecca Sowah', 'female', 34, 'Maternal health', 'Sogakope', 'Volta'], ['Demo: Abena Kyei', 'female', 28, 'Maternal health', 'Sogakope', 'Volta'],
            ['Demo: Patience Dogbe', 'female', 31, 'Maternal health', 'Ho', 'Volta'], ['Demo: Ebenezer Nkrumah', 'male', 52, 'Clean water', 'Tafi Atome', 'Volta'],
            ['Demo: Adwoa Boakye', 'female', 45, 'Clean water', 'Adaklu', 'Volta'], ['Demo: Fiifi Mensah', 'male', 16, 'Youth coding club', 'Accra', 'Greater Accra'],
            ['Demo: Naa Lamiokor', 'female', 15, 'Youth coding club', 'Accra', 'Greater Accra'], ['Demo: Joseph Agyeman', 'male', 38, 'Maternal health', 'Ho', 'Volta'],
        ];

        $registered = [];
        foreach ($people as $index => [$name, $gender, $age, $programme, $community, $region]) {
            $person = $service->register($tenantId, $name, [
                'gender' => $gender, 'date_of_birth' => today()->subYears($age)->subDays(40 + $index)->toDateString(), 'programme' => $programme,
                'community' => $community, 'region' => $region, 'household_size' => 3 + ($index % 5), 'phone' => '+23320'.random_int(1000000, 9999999),
                'enrolled_on' => today()->subMonths(2 + ($index % 6))->toDateString(),
            ], null);
            $registered[] = $person;

            if ($index !== 11) {
                $service->recordConsent($person, 'Receive services and be included in anonymous programme reports', today()->subMonths(2), null);
            }
        }

        $types = ['School feeding' => ['food', 'education'], 'Maternal health' => ['medical', 'counselling'], 'Clean water' => ['training', 'other'], 'Youth coding club' => ['training', 'education']];
        foreach ($registered as $index => $person) {
            if ($index === 11) {
                continue;
            }
            foreach ([40, 25, 9] as $step => $daysAgo) {
                $type = $types[$person->programme][$step % 2];
                $service->recordService($person, today()->subDays($daysAgo + $index), $type, null, $type === 'cash' ? null : null, null);
            }
        }

        $case = $service->openCase($registered[4], 'Antenatal follow-up: high blood pressure', 'health', 'high', null);
        $service->addNote($case, 'Referred to Ho Teaching Hospital for review.', false, null);
        $service->addNote($case, 'Shared concern about her husband\'s consent for hospital visits.', true, null);
        $case2 = $service->openCase($registered[1], 'Missing school: transport', 'education', 'normal', null);
        $service->addNote($case2, 'Distance of 6km. Exploring a bicycle through the youth fund.', false, null);
        $done = $service->openCase($registered[8], 'Water committee training follow-up', 'livelihood', 'low', null);
        $service->closeCase($done, 'Committee trained and tools handed over', null);

        $this->command?->info('BeneficiariesDemoSeeder: '.count($registered).' beneficiaries, services and cases created.');
    }
}
