<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Demo\Sections;

use App\Models\Tenant;
use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoSection;
use App\Modules\SiteThyroidGhanaFoundation\Demo\RowRemover;
use App\Modules\Volunteers\Models\VolOpportunity;
use App\Modules\Volunteers\Services\VolunteerService;
use Carbon\CarbonImmutable;

/** Approved volunteers, two public sign-ups awaiting approval, opportunities and logged hours. */
final class VolunteersSection implements DemoSection
{
    public function label(): string
    {
        return 'Volunteers';
    }

    public function seed(Tenant $tenant): void
    {
        $tenantId = (string) $tenant->id;
        $service = app(VolunteerService::class);
        $people = [];
        foreach ([
            ['Demo: Akosua Darko', 'Nursing, patient counselling'], ['Demo: Nii Armah', 'Driving, logistics'], ['Demo: Esi Quartey', 'Nursing, first aid'],
            ['Demo: Kojo Bediako', 'Photography, social media'], ['Demo: Mariam Abdul', 'Event help, registration'], ['Demo: Yaa Pokua', 'Fundraising'],
            ['Demo: Samuel Tetteh', 'Setup and logistics'], ['Demo: Linda Owusu', 'Translation (Twi, Ewe, English)'],
        ] as $index => [$name, $skills]) {
            $people[] = $service->register($tenantId, $name, ['email' => 'volunteer'.($index + 1).'@example.test', 'skills' => $skills, 'phone' => '+23324'.random_int(1000000, 9999999)], true);
        }
        $service->register($tenantId, 'Demo: Public Sign-up One', ['email' => 'signup1@example.test', 'skills' => 'Willing to help anywhere'], false);
        $service->register($tenantId, 'Demo: Public Sign-up Two', ['email' => 'signup2@example.test', 'skills' => 'Graphic design'], false);

        $camp = VolOpportunity::query()->create(['tenant_id' => $tenantId, 'title' => 'Demo: Screening camp, Kumasi', 'location' => 'Kumasi', 'description' => 'Registration, crowd flow and patient education.', 'starts_on' => today()->addDays(9), 'spots' => 4, 'status' => 'open']);
        $forum = VolOpportunity::query()->create(['tenant_id' => $tenantId, 'title' => 'Demo: Patients forum evening', 'location' => 'Accra', 'starts_on' => today()->addDays(3), 'spots' => 0, 'status' => 'open']);
        $past = VolOpportunity::query()->create(['tenant_id' => $tenantId, 'title' => 'Demo: World Thyroid Awareness Week walk', 'location' => 'Accra', 'starts_on' => today()->subDays(20), 'spots' => 6, 'status' => 'closed']);

        foreach ([0, 1, 2, 4] as $i) {
            $service->signUp($camp, $people[$i]);
        }
        foreach ([0, 3, 4, 5, 7] as $i) {
            $service->signUp($forum, $people[$i]);
        }

        $hourPlan = [[0, 6, 4], [0, 12, 3.5], [1, 5, 8], [1, 20, 6], [2, 9, 5], [2, 21, 5], [3, 15, 3], [4, 7, 4.5], [4, 19, 6], [5, 30, 2], [6, 25, 7], [7, 10, 2.5]];
        foreach ($hourPlan as $index => [$who, $daysAgo, $hours]) {
            $entry = $service->logHours($people[$who], today()->subDays($daysAgo), (float) $hours, 'Volunteer shift', $index % 3 === 0 ? $past : null);
            if ($index < 9) {
                $service->review($entry, true, null);
            }
        }
    }

    public function remove(Tenant $tenant, CarbonImmutable $since): int
    {
        $remover = new RowRemover;

        return $remover->children('vol_hours', 'volunteer_id', 'vol_volunteers', $tenant, $since, 'name')
            + $remover->children('vol_signups', 'volunteer_id', 'vol_volunteers', $tenant, $since, 'name')
            + $remover->children('vol_signups', 'opportunity_id', 'vol_opportunities', $tenant, $since, 'title')
            + $remover->rows('vol_opportunities', $tenant, $since, 'title') + $remover->rows('vol_volunteers', $tenant, $since, 'name');
    }
}
