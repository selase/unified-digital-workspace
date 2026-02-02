<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Database\Seeders;

use App\Models\Tenant;
use App\Modules\HrmsCore\Models\Organization\Grade;
use Illuminate\Database\Seeder;

final class GradeSeeder extends Seeder
{
    /**
     * Default grades for Ghanaian civil service-style organizations.
     *
     * @var array<int, array{name: string, can_recommend_leave: bool, can_approve_leave: bool, can_appraise: bool}>
     */
    private array $defaultGrades = [
        ['name' => 'Director', 'can_recommend_leave' => true, 'can_approve_leave' => true, 'can_appraise' => true],
        ['name' => 'Deputy Director', 'can_recommend_leave' => true, 'can_approve_leave' => true, 'can_appraise' => true],
        ['name' => 'Assistant Director', 'can_recommend_leave' => true, 'can_approve_leave' => false, 'can_appraise' => true],
        ['name' => 'Principal Officer', 'can_recommend_leave' => true, 'can_approve_leave' => false, 'can_appraise' => true],
        ['name' => 'Senior Officer', 'can_recommend_leave' => true, 'can_approve_leave' => false, 'can_appraise' => false],
        ['name' => 'Officer', 'can_recommend_leave' => false, 'can_approve_leave' => false, 'can_appraise' => false],
        ['name' => 'Assistant Officer', 'can_recommend_leave' => false, 'can_approve_leave' => false, 'can_appraise' => false],
        ['name' => 'Junior Officer', 'can_recommend_leave' => false, 'can_approve_leave' => false, 'can_appraise' => false],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->seedGradesForTenant($tenant);
        }
    }

    /**
     * Seed grades for a specific tenant.
     */
    public function seedGradesForTenant(Tenant $tenant): void
    {
        foreach ($this->defaultGrades as $index => $gradeData) {
            Grade::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $gradeData['name'],
                ],
                [
                    'can_recommend_leave' => $gradeData['can_recommend_leave'],
                    'can_approve_leave' => $gradeData['can_approve_leave'],
                    'can_appraise' => $gradeData['can_appraise'],
                    'sort_order' => $index + 1,
                ]
            );
        }
    }
}
