<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Demo\Sections;

use App\Models\Tenant;
use App\Modules\Beneficiaries\Services\BeneficiaryService;
use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoSection;
use App\Modules\SiteThyroidGhanaFoundation\Demo\RowRemover;
use Carbon\CarbonImmutable;

/** A consent-gated register with services and cases. Programme labels only: no medical details are invented. */
final class BeneficiariesSection implements DemoSection
{
    public function label(): string
    {
        return 'Beneficiaries';
    }

    public function seed(Tenant $tenant): void
    {
        $tenantId = (string) $tenant->id;
        $service = app(BeneficiaryService::class);
        $people = [
            ['Demo: Ama Konadu', 'female', 41, 'Screening camps', 'Kumasi', 'Ashanti'], ['Demo: Kwesi Ampofo', 'male', 29, 'Screening camps', 'Kumasi', 'Ashanti'],
            ['Demo: Mavis Tsikata', 'female', 36, 'Screening camps', 'Ho', 'Volta'], ['Demo: Daniel Gyamfi', 'male', 52, 'Screening camps', 'Accra', 'Greater Accra'],
            ['Demo: Rebecca Sowah', 'female', 34, 'Subsidised surgery', 'Accra', 'Greater Accra'], ['Demo: Abena Kyei', 'female', 28, 'Subsidised surgery', 'Tema', 'Greater Accra'],
            ['Demo: Patience Dogbe', 'female', 47, 'Subsidised surgery', 'Ho', 'Volta'], ['Demo: Ebenezer Nkrumah', 'male', 58, 'Patient support group', 'Accra', 'Greater Accra'],
            ['Demo: Adwoa Boakye', 'female', 45, 'Patient support group', 'Kumasi', 'Ashanti'], ['Demo: Fiifi Mensah', 'male', 33, 'Patient support group', 'Accra', 'Greater Accra'],
            ['Demo: Naa Lamiokor', 'female', 39, 'Patient support group', 'Tema', 'Greater Accra'], ['Demo: Joseph Agyeman', 'male', 38, 'Screening camps', 'Ho', 'Volta'],
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

        $types = ['Screening camps' => ['medical', 'counselling'], 'Subsidised surgery' => ['medical', 'other'], 'Patient support group' => ['counselling', 'training']];
        foreach ($registered as $index => $person) {
            if ($index === 11) {
                continue;
            }
            foreach ([40, 25, 9] as $step => $daysAgo) {
                $service->recordService($person, today()->subDays($daysAgo + $index), $types[$person->programme][$step % 2], null, null, null);
            }
        }

        $case = $service->openCase($registered[4], 'Post-surgery follow-up visit', 'health', 'high', null);
        $service->addNote($case, 'Follow-up booked with the surgical team.', false, null);
        $service->addNote($case, 'Shared a worry about the cost of travel to appointments.', true, null);
        $case2 = $service->openCase($registered[1], 'Transport to the screening camp', 'livelihood', 'normal', null);
        $service->addNote($case2, 'Distance of 40km. Exploring volunteer drivers.', false, null);
        $done = $service->openCase($registered[8], 'Patient group onboarding follow-up', 'education', 'low', null);
        $service->closeCase($done, 'Joined the monthly forum', null);
    }

    public function remove(Tenant $tenant, CarbonImmutable $since): int
    {
        $remover = new RowRemover;
        $removed = 0;

        $cases = $remover->idsBelongingTo('ben_cases', 'beneficiary_id', $remover->demoIds('ben_beneficiaries', $tenant, $since, 'full_name'));
        $removed += $remover->deleteWhereIn('ben_case_notes', 'case_id', $cases);

        foreach (['ben_access_log', 'ben_documents', 'ben_referrals', 'ben_cases', 'ben_services'] as $child) {
            $removed += $remover->children($child, 'beneficiary_id', 'ben_beneficiaries', $tenant, $since, 'full_name');
        }

        return $removed + $remover->rows('ben_beneficiaries', $tenant, $since, 'full_name');
    }
}
