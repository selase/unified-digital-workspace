<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Demo\Sections;

use App\Models\Tenant;
use App\Modules\Governance\Services\GovernanceService;
use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoSection;
use App\Modules\SiteThyroidGhanaFoundation\Demo\RowRemover;
use Carbon\CarbonImmutable;

/** A fictional board, a held meeting with approved minutes and resolutions, and the actions agreed at it. */
final class GovernanceSection implements DemoSection
{
    public function label(): string
    {
        return 'Governance';
    }

    public function seed(Tenant $tenant): void
    {
        $tenantId = (string) $tenant->id;
        $service = app(GovernanceService::class);
        $members = [];
        foreach ([['Demo: Dr. Kofi Mensah-Addo', 'Chair', 400], ['Demo: Abena Osei', 'Treasurer', 60], ['Demo: Yaw Boateng', 'Secretary', 700], ['Demo: Efua Quartey', 'Member', 900], ['Demo: Nii Armah', 'Member', 45]] as [$name, $position, $termEndsInDays]) {
            $members[] = $service->addMember($tenantId, $name, ['position' => $position, 'term_starts_on' => today()->subYears(2)->toDateString(), 'term_ends_on' => today()->addDays($termEndsInDays)->toDateString(), 'email' => str($name)->after('Demo: ')->slug('.').'@example.test']);
        }

        $held = $service->schedule($tenantId, 'Demo: Q3 board meeting', 'Board', today()->subDays(40)->setTime(10, 0), 'Accra, boardroom', 3, "1. Welcome and apologies\n2. Minutes of the last meeting\n3. Finance report\n4. Programme update\n5. Any other business");
        $service->recordAttendance($held, [$members[0]->id => 'present', $members[1]->id => 'present', $members[2]->id => 'present', $members[3]->id => 'apologies', $members[4]->id => 'absent']);
        $service->markHeld($held);
        $service->saveMinutes($held, "The Chair opened the meeting at 10:05.\nThe Treasurer presented the quarter's accounts; giving was ahead of budget.\nThe board discussed the subsidised surgery programme and agreed to extend it to a second hospital.");
        $service->approveMinutes($held, today()->subDays(10));
        $service->addResolution($held, 'The board approved the Q2 accounts as presented.', 'passed');
        $service->addResolution($held, 'The board approved extending subsidised surgeries to a second hospital.', 'passed');
        $service->addAction($tenantId, 'Prepare a funding plan for the second hospital', $held, $members[1], null, today()->addDays(14));
        $service->addAction($tenantId, 'Send the approved accounts to the auditor', $held, $members[2], null, today()->subDays(5));

        $committee = $service->schedule($tenantId, 'Demo: Finance committee', 'Finance committee', today()->subDays(3)->setTime(14, 0), 'Online', 2, "1. Budget review\n2. Audit timetable");
        $service->recordAttendance($committee, [$members[1]->id => 'present', $members[2]->id => 'present']);
        $service->markHeld($committee);

        $service->schedule($tenantId, 'Demo: Annual general meeting', 'AGM', today()->addDays(30)->setTime(10, 0), 'Accra, conference hall', 5, "1. Annual report\n2. Elections\n3. Auditor appointment");
    }

    public function remove(Tenant $tenant, CarbonImmutable $since): int
    {
        $remover = new RowRemover;
        $removed = 0;

        foreach (['gov_attendance', 'gov_actions', 'gov_resolutions'] as $child) {
            $removed += $remover->children($child, 'meeting_id', 'gov_meetings', $tenant, $since, 'title');
        }
        $removed += $remover->children('gov_attendance', 'member_id', 'gov_members', $tenant, $since, 'name');

        return $removed + $remover->rows('gov_meetings', $tenant, $since, 'title') + $remover->rows('gov_members', $tenant, $since, 'name');
    }
}
