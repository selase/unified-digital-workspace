<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Support;

/** The leadership details shown on Our Team and Our Board. */
final class TeamRoster
{
    /** @return list<array{name: string, role: string, photo: string, bio: string}> */
    public static function management(): array
    {
        return [
            [
                'name' => 'Dr. Nana Adwoa Konadu Dsane',
                'role' => 'Founder & President',
                'photo' => 'cms/team/nana-adjoa.jpg',
                'bio' => 'Established the foundation in 2018 following her personal battle with hyperthyroidism. A seasoned administrator with over 20 years of experience across research, health, and education.',
            ],
            [
                'name' => 'Rev. Prof. Patrick F. Ayeh-Kumi',
                'role' => 'Management Board Chair',
                'photo' => 'cms/team/prof-patrick.jpg',
                'bio' => 'A distinguished academic and researcher who brings decades of medical expertise to the foundation\'s governance and strategic direction.',
            ],
            [
                'name' => 'Mr. Leslie Chartey Kumahlor',
                'role' => 'Head of Operations',
                'photo' => 'cms/team/lesley-chartey.jpg',
                'bio' => 'Oversees the day-to-day operations and ensures our programs reach communities across Ghana effectively.',
            ],
            [
                'name' => 'Dr. Joyce Emefa Addo-Klah',
                'role' => 'Public Relations Officer',
                'photo' => 'cms/team/joyce-emefa.jpg',
                'bio' => 'Leads our communications strategy and public engagement initiatives across media platforms.',
            ],
            [
                'name' => 'Frank Anyimadu',
                'role' => 'Consultant Dietician',
                'photo' => 'cms/team/frank-anyimedu.jpg',
                'bio' => 'Provides nutritional guidance and develops dietary programs tailored for thyroid patients.',
            ],
            [
                'name' => 'Justice Kwesi Baah',
                'role' => 'Research Coordinator',
                'photo' => 'cms/team/justice.jpg',
                'bio' => 'Coordinates research initiatives and partnerships with academic institutions across Ghana.',
            ],
        ];
    }

    /** @return list<array{name: string, role: string}> */
    public static function advisors(): array
    {
        return [
            ['name' => 'Dr. Josephine Akpalu', 'role' => 'Advisory Board Chair'],
            ['name' => 'Dr. Alfred Tetteh', 'role' => 'Member, Management Board'],
            ['name' => 'Dr. Matilda Asante', 'role' => 'Member, Management Board'],
        ];
    }

    /** @return array{chair: array{name: string, role: string, photo: string, bio: string}, members: list<array{name: string, role: string}>, advisoryChair: array{name: string, role: string}} */
    public static function board(): array
    {
        $advisors = collect(self::advisors());

        return [
            'chair' => collect(self::management())->firstWhere('role', 'Management Board Chair'),
            'members' => $advisors->filter(fn (array $advisor): bool => str_starts_with($advisor['role'], 'Member, Management Board'))->values()->all(),
            'advisoryChair' => $advisors->firstWhere('role', 'Advisory Board Chair'),
        ];
    }
}
