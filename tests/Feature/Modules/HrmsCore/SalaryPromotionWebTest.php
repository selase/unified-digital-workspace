<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\HrmsCore\Enums\PromotionCategory;
use App\Modules\HrmsCore\Enums\PromotionStatus;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Organization\Grade;
use App\Modules\HrmsCore\Models\Promotion\StaffPromotion;
use App\Modules\HrmsCore\Models\Salary\Allowance;
use App\Modules\HrmsCore\Models\Salary\AllowanceType;
use App\Modules\HrmsCore\Models\Salary\EmployeeAllowance;
use App\Modules\HrmsCore\Models\Salary\SalaryLevel;
use App\Modules\HrmsCore\Models\Salary\SalaryStep;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Session;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->hrUser = User::factory()->create(['email' => 'hr.compensation@example.com']);
    $this->tenant = setActiveTenantForTest($this->hrUser);

    Session::put('active_tenant_id', $this->tenant->id);
    setPermissionsTeamId($this->tenant->id);
    app(TenantContext::class)->setTenant($this->tenant);
    app(ModuleManager::class)->enableForTenant('hrms-core', $this->tenant);

    $permissions = [
        'hrms.salary.view',
        'hrms.salary.manage',
        'hrms.allowances.view',
        'hrms.allowances.manage',
        'hrms.promotion.view',
        'hrms.promotion.request',
        'hrms.promotion.recommend',
        'hrms.promotion.approve',
    ];

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(
            ['name' => $perm, 'guard_name' => 'web'],
            ['category' => 'hrms-core']
        );
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $role = Role::firstOrCreate([
        'name' => 'HR Compensation & Promotion Admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $role->syncPermissions($permissions);
    $this->hrUser->assignRole($role);

    $this->juniorGrade = Grade::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Officer I',
        'slug' => 'officer-i',
        'sort_order' => 1,
    ]);
    $this->seniorGrade = Grade::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Senior Officer',
        'slug' => 'senior-officer',
        'sort_order' => 2,
    ]);

    $this->employee = Employee::factory()->forTenant($this->tenant->id)->create([
        'first_name' => 'Ama',
        'last_name' => 'Darko',
        'grade_id' => $this->juniorGrade->id,
    ]);
    $this->hrEmployee = Employee::factory()->forTenant($this->tenant->id)->create([
        'first_name' => 'Kwadwo',
        'last_name' => 'Mensah',
        'user_id' => $this->hrUser->id,
        'email' => $this->hrUser->email,
    ]);
});

test('can manage salary levels, steps, allowance types, allowances, and assign employee compensation', function (): void {
    // 1. Create Salary Level
    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.salary.levels.store'), [
            'name' => 'Level 8 Officer Scale',
            'grade_id' => $this->juniorGrade->id,
            'base_salary' => 4500.00,
            'description' => 'Entry officer base salary',
            'sort_order' => 1,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $level = SalaryLevel::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
    expect((float) $level->base_salary)->toBe(4500.00);

    // Update Salary Level
    $this->actingAs($this->hrUser)
        ->put(route('hrms-core.salary.levels.update', $level), [
            'name' => 'Level 8 Officer Scale (Revised)',
            'grade_id' => $this->juniorGrade->id,
            'base_salary' => 4800.00,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect((float) $level->fresh()?->base_salary)->toBe(4800.00);

    // 2. Create Salary Step
    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.salary.steps.store'), [
            'name' => 'Step 2',
            'salary_level_id' => $level->id,
            'step_number' => 2,
            'step_increment' => 350.00,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $step = SalaryStep::query()->where('salary_level_id', $level->id)->firstOrFail();
    expect($step->calculateTotalSalary())->toBe(5150.00);

    // 3. Assign Employee Compensation (and reject mismatched step/level)
    $otherLevel = SalaryLevel::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Level 9 Officer Scale II',
        'grade_id' => $this->juniorGrade->id,
        'base_salary' => 5200.00,
    ]);

    $this->actingAs($this->hrUser)
        ->put(route('hrms-core.salary.employee-compensation.update'), [
            'employee_id' => $this->employee->id,
            'grade_id' => $this->juniorGrade->id,
            'salary_level_id' => $otherLevel->id,
            'salary_step_id' => $step->id,
        ])
        ->assertSessionHasErrors('salary_step_id');

    $this->actingAs($this->hrUser)
        ->put(route('hrms-core.salary.employee-compensation.update'), [
            'employee_id' => $this->employee->id,
            'grade_id' => $this->juniorGrade->id,
            'salary_level_id' => $level->id,
            'salary_step_id' => $step->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->employee->refresh();
    expect($this->employee->salary_level_id)->toBe($level->id)
        ->and($this->employee->salary_step_id)->toBe($step->id);

    $compensationActivity = Activity::query()
        ->where('log_name', 'hrms-salary')
        ->where('subject_type', Employee::class)
        ->where('subject_id', $this->employee->id)
        ->where('event', 'salary.compensation.updated')
        ->first();

    expect($compensationActivity)->not->toBeNull()
        ->and($compensationActivity->causer_id)->toBe($this->hrUser->id)
        ->and($compensationActivity->properties['old']['salary_level_id'])->toBeNull()
        ->and($compensationActivity->properties['new']['salary_level_id'])->toBe($level->id)
        ->and($compensationActivity->properties['new']['salary_step_id'])->toBe($step->id);

    // 4. Create Allowance Type & Allowance Definition
    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.salary.allowance-types.store'), [
            'name' => 'Housing & Rent',
            'is_taxable' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $allowanceType = AllowanceType::query()->where('tenant_id', $this->tenant->id)->firstOrFail();

    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.salary.allowances.store'), [
            'name' => 'Monthly Rent Supplement',
            'allowance_type_id' => $allowanceType->id,
            'grade_id' => $this->juniorGrade->id,
            'amount' => 800.00,
            'frequency' => Allowance::FREQUENCY_MONTHLY,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $allowance = Allowance::query()->where('tenant_id', $this->tenant->id)->firstOrFail();

    // 5. Assign Allowance to Employee with custom override amount
    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.salary.employee-allowances.store'), [
            'employee_id' => $this->employee->id,
            'allowance_id' => $allowance->id,
            'amount' => 950.00,
            'effective_from' => '2026-01-01',
            'notes' => 'Approved relocation supplement',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $empAllowance = EmployeeAllowance::query()->where('employee_id', $this->employee->id)->firstOrFail();
    expect($empAllowance->getEffectiveAmount())->toBe(950.00);

    // Update Employee Allowance
    $this->actingAs($this->hrUser)
        ->put(route('hrms-core.salary.employee-allowances.update', $empAllowance), [
            'amount' => 1050.00,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'is_active' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($empAllowance->fresh()?->getEffectiveAmount())->toBe(1050.00);
});

test('enforces party-based confidentiality on employee compensation and allowances', function (): void {
    $level = SalaryLevel::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Confidential Level',
        'base_salary' => 6000.00,
    ]);
    $allowanceType = AllowanceType::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Transport',
    ]);
    $allowance = Allowance::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Fuel Allowance',
        'allowance_type_id' => $allowanceType->id,
        'amount' => 500.00,
        'frequency' => Allowance::FREQUENCY_MONTHLY,
    ]);

    $employeeUser = User::factory()->create(['email' => 'ama.darko@example.com']);
    $this->tenant->users()->attach($employeeUser->id);
    $this->employee->update([
        'user_id' => $employeeUser->id,
        'email' => $employeeUser->email,
        'salary_level_id' => $level->id,
    ]);

    EmployeeAllowance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'allowance_id' => $allowance->id,
        'amount' => 1234.56,
        'effective_from' => '2026-01-01',
        'notes' => 'AMA_PRIVATE_ALLOWANCE_NOTE',
    ]);

    // Colleague with hrms.salary.view and hrms.allowances.view (not manage)
    $colleagueUser = User::factory()->create(['email' => 'colleague.viewer@example.com']);
    $this->tenant->users()->attach($colleagueUser->id);
    Employee::factory()->forTenant($this->tenant->id)->create([
        'first_name' => 'Kojo',
        'last_name' => 'Antwi',
        'user_id' => $colleagueUser->id,
        'email' => $colleagueUser->email,
    ]);

    $viewerRole = Role::firstOrCreate([
        'name' => 'Salary Catalog Viewer',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $viewerRole->syncPermissions(['hrms.salary.view', 'hrms.allowances.view']);
    $colleagueUser->assignRole($viewerRole);

    // 1. Colleague with view permission sees the catalog (Fuel Allowance) but NEVER sees Ama's employee allowance or private note
    $this->actingAs($colleagueUser)
        ->get(route('hrms-core.salary.index'))
        ->assertOk()
        ->assertSee('Fuel Allowance')
        ->assertDontSee('AMA_PRIVATE_ALLOWANCE_NOTE')
        ->assertDontSee('1,234.56');

    // 2. Colleague attempting to filter by Ama's employee_id gets 403
    $this->actingAs($colleagueUser)
        ->get(route('hrms-core.salary.index', ['employee_id' => $this->employee->id]))
        ->assertForbidden();

    // 3. Ama herself can view her own compensation and private allowance note via My Compensation
    $this->actingAs($employeeUser)
        ->get(route('hrms-core.salary.index', ['scope' => 'mine']))
        ->assertOk()
        ->assertSee('AMA_PRIVATE_ALLOWANCE_NOTE')
        ->assertSee('1,234.56');
});

test('can initiate staff promotion, progress through supervisor and HR approval, and synchronize employee grade and salary level', function (): void {
    $fromLevel = SalaryLevel::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Level 8 Junior Scale',
        'grade_id' => $this->juniorGrade->id,
        'base_salary' => 4500.00,
    ]);
    $toLevel = SalaryLevel::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Level 10 Senior Scale',
        'grade_id' => $this->seniorGrade->id,
        'base_salary' => 6800.00,
    ]);

    $this->employee->update([
        'grade_id' => $this->juniorGrade->id,
        'salary_level_id' => $fromLevel->id,
    ]);

    $supervisorUser = User::factory()->create(['email' => 'promo.supervisor@example.com']);
    $this->tenant->users()->attach($supervisorUser->id);
    $supervisorEmployee = Employee::factory()->forTenant($this->tenant->id)->create([
        'first_name' => 'Abena',
        'last_name' => 'Osei',
        'user_id' => $supervisorUser->id,
        'email' => $supervisorUser->email,
    ]);

    $supervisorRole = Role::firstOrCreate([
        'name' => 'Promotion Recommender',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $supervisorRole->syncPermissions(['hrms.promotion.view', 'hrms.promotion.request', 'hrms.promotion.recommend']);
    $supervisorUser->assignRole($supervisorRole);

    // 1. Supervisor initiates promotion request for Ama
    $this->actingAs($supervisorUser)
        ->post(route('hrms-core.promotions.store'), [
            'employee_id' => $this->employee->id,
            'category' => PromotionCategory::Promotion->value,
            'to_grade_id' => $this->seniorGrade->id,
            'to_salary_level_id' => $toLevel->id,
            'supervisor_id' => $supervisorEmployee->id,
            'effective_date' => '2026-07-01',
            'reason' => 'Exceeded annual targets for 2 consecutive cycles',
            'justification' => 'Top appraisal rating (4.8/5.0)',
            'submit_for_review' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $promotion = StaffPromotion::query()->where('employee_id', $this->employee->id)->firstOrFail();
    expect($promotion->status)->toBe(PromotionStatus::AwaitingSupervisorApproval)
        ->and($promotion->from_grade_id)->toBe($this->juniorGrade->id)
        ->and($promotion->from_salary_level_id)->toBe($fromLevel->id);

    // Cannot jump straight to HR approval while still AwaitingSupervisorApproval
    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.promotions.hr-review', $promotion), [
            'decision' => 'approve',
        ])
        ->assertSessionHasErrors('status');

    // 2. Supervisor approves -> moves to AwaitingHrApproval
    $this->actingAs($supervisorUser)
        ->post(route('hrms-core.promotions.supervisor-review', $promotion), [
            'decision' => 'approve',
            'comments' => 'Strongly recommended for Senior Officer.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $promotion->refresh();
    expect($promotion->status)->toBe(PromotionStatus::AwaitingHrApproval)
        ->and($promotion->supervisor_approved)->toBeTrue()
        ->and($promotion->supervisor_id)->toBe($supervisorEmployee->id);

    // Supervisor cannot perform final HR approval (403)
    $this->actingAs($supervisorUser)
        ->post(route('hrms-core.promotions.hr-review', $promotion), [
            'decision' => 'approve',
        ])
        ->assertForbidden();

    // 3. HR approves -> moves to Approved and updates employee's grade_id and salary_level_id
    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.promotions.hr-review', $promotion), [
            'decision' => 'approve',
            'comments' => 'Approved by HR Promotion Board.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $promotion->refresh();
    $this->employee->refresh();

    expect($promotion->status)->toBe(PromotionStatus::Approved)
        ->and($promotion->hr_approved)->toBeTrue()
        ->and($promotion->hr_approver_id)->toBe($this->hrEmployee->id)
        ->and($this->employee->grade_id)->toBe($this->seniorGrade->id)
        ->and($this->employee->salary_level_id)->toBe($toLevel->id);
});

test('enforces promotion party visibility, self-review prohibition, unlinked actor rejection, and cross-tenant isolation', function (): void {
    $employeeUser = User::factory()->create(['email' => 'ama.promo@example.com']);
    $this->tenant->users()->attach($employeeUser->id);
    $this->employee->update([
        'user_id' => $employeeUser->id,
        'email' => $employeeUser->email,
    ]);

    $colleagueUser = User::factory()->create(['email' => 'colleague.promo@example.com']);
    $this->tenant->users()->attach($colleagueUser->id);
    $colleagueEmployee = Employee::factory()->forTenant($this->tenant->id)->create([
        'first_name' => 'Fiifi',
        'last_name' => 'Quansah',
        'user_id' => $colleagueUser->id,
        'email' => $colleagueUser->email,
    ]);

    $colleagueRole = Role::firstOrCreate([
        'name' => 'Promotion Viewer & Recommender',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $colleagueRole->syncPermissions(['hrms.promotion.view', 'hrms.promotion.request', 'hrms.promotion.recommend']);
    $colleagueUser->assignRole($colleagueRole);

    $promotion = StaffPromotion::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'supervisor_id' => $this->hrEmployee->id,
        'category' => PromotionCategory::Promotion,
        'status' => PromotionStatus::AwaitingSupervisorApproval,
        'reason' => 'CONFIDENTIAL_PROMOTION_DOSSIER_REASON',
    ]);

    // 1. Colleague with hrms.promotion.view + recommend who is NOT the assigned supervisor cannot view Ama's promotion (403)
    $this->actingAs($colleagueUser)
        ->get(route('hrms-core.promotions.show', $promotion))
        ->assertForbidden();

    $this->actingAs($colleagueUser)
        ->get(route('hrms-core.promotions.index'))
        ->assertOk()
        ->assertDontSee('CONFIDENTIAL_PROMOTION_DOSSIER_REASON')
        ->assertSee('No promotion requests found in this scope.');

    $this->actingAs($this->hrUser)
        ->get(route('hrms-core.promotions.index'))
        ->assertOk()
        ->assertSee('CONFIDENTIAL_PROMOTION_DOSSIER_REASON');

    // 2. Colleague cannot review Ama's promotion when another supervisor is assigned -> 403
    $this->actingAs($colleagueUser)
        ->post(route('hrms-core.promotions.supervisor-review', $promotion), [
            'decision' => 'approve',
        ])
        ->assertForbidden();

    // 3. Employee cannot approve their own promotion as supervisor even if given hrms.promotion.recommend -> 403
    $employeeUser->assignRole($colleagueRole);
    $selfPromotion = StaffPromotion::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'supervisor_id' => $this->employee->id,
        'category' => PromotionCategory::Upgrade,
        'status' => PromotionStatus::AwaitingSupervisorApproval,
        'reason' => 'Self upgrade attempt',
    ]);

    $this->actingAs($employeeUser)
        ->post(route('hrms-core.promotions.supervisor-review', $selfPromotion), [
            'decision' => 'approve',
        ])
        ->assertForbidden();

    // 4. HR override on supervisor review is audit-logged
    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.promotions.supervisor-review', $selfPromotion), [
            'decision' => 'approve',
            'comments' => 'HR override for supervisor review',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $overrideLog = Activity::query()
        ->where('log_name', 'hrms-promotion')
        ->where('subject_type', StaffPromotion::class)
        ->where('subject_id', $selfPromotion->id)
        ->where('event', 'promotion.supervisor_review.override')
        ->first();

    expect($overrideLog)->not->toBeNull();

    // 5. Unlinked HR user with no Employee record is rejected with 422 (never attributed to another employee)
    $unlinkedHr = User::factory()->create(['email' => 'unlinked.promo.hr@example.com']);
    $this->tenant->users()->attach($unlinkedHr->id);
    $hrRole = Role::query()->where('name', 'HR Compensation & Promotion Admin')->where('tenant_id', $this->tenant->id)->firstOrFail();
    $unlinkedHr->assignRole($hrRole);

    $this->actingAs($unlinkedHr)
        ->post(route('hrms-core.promotions.hr-review', $selfPromotion), [
            'decision' => 'approve',
        ])
        ->assertSessionHasErrors('employee');

    // 6. Cross-tenant isolation: Tenant B promotion returns 404 in Tenant A
    $otherTenant = Tenant::factory()->create([
        'name' => 'Other Promo Tenant',
        'slug' => 'other-promo-tenant',
    ]);
    app(TenantContext::class)->setTenant($otherTenant);
    $otherEmployee = Employee::factory()->forTenant($otherTenant->id)->create();
    $otherPromotion = StaffPromotion::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $otherEmployee->id,
        'category' => PromotionCategory::Promotion,
        'status' => PromotionStatus::Pending,
        'reason' => 'Other tenant promotion',
    ]);
    app(TenantContext::class)->setTenant($this->tenant);

    $this->actingAs($this->hrUser)
        ->get(route('hrms-core.promotions.show', $otherPromotion))
        ->assertNotFound();

    // 7. Reject promotion creation with neither proposed grade nor proposed salary level
    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.promotions.store'), [
            'employee_id' => $colleagueEmployee->id,
            'category' => PromotionCategory::Promotion->value,
            'reason' => 'Missing both target grade and salary level',
        ])
        ->assertSessionHasErrors('to_grade_id');

    // 8. Reject promotion creation where employee is assigned as their own supervisor
    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.promotions.store'), [
            'employee_id' => $colleagueEmployee->id,
            'category' => PromotionCategory::Promotion->value,
            'to_grade_id' => $this->seniorGrade->id,
            'supervisor_id' => $colleagueEmployee->id,
            'reason' => 'Self-assigned supervisor attempt',
        ])
        ->assertSessionHasErrors('supervisor_id');

    // 9. Reject promotion creation where proposed salary level belongs to a different grade than proposed grade
    $juniorOnlyLevel = SalaryLevel::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Junior Only Scale',
        'grade_id' => $this->juniorGrade->id,
        'base_salary' => 4200.00,
    ]);

    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.promotions.store'), [
            'employee_id' => $colleagueEmployee->id,
            'category' => PromotionCategory::Promotion->value,
            'to_grade_id' => $this->seniorGrade->id,
            'to_salary_level_id' => $juniorOnlyLevel->id,
            'reason' => 'Mismatched grade and salary level',
        ])
        ->assertSessionHasErrors('to_salary_level_id');

    // 10. Reject duplicate open promotion request when employee already has an active promotion in flight
    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.promotions.store'), [
            'employee_id' => $this->employee->id,
            'category' => PromotionCategory::Promotion->value,
            'to_grade_id' => $this->seniorGrade->id,
            'reason' => 'Duplicate active promotion attempt',
        ])
        ->assertSessionHasErrors('employee_id');

    // 11. Reject employee compensation assignment when salary level grade conflicts with employee grade or step has no matching salary level
    $stepForJunior = SalaryStep::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Step 1',
        'salary_level_id' => $juniorOnlyLevel->id,
        'grade_id' => $this->juniorGrade->id,
        'step_number' => 1,
        'step_increment' => 200.00,
    ]);

    $this->actingAs($this->hrUser)
        ->put(route('hrms-core.salary.employee-compensation.update'), [
            'employee_id' => $colleagueEmployee->id,
            'grade_id' => $this->seniorGrade->id,
            'salary_level_id' => $juniorOnlyLevel->id,
        ])
        ->assertSessionHasErrors('salary_level_id');

    $this->actingAs($this->hrUser)
        ->put(route('hrms-core.salary.employee-compensation.update'), [
            'employee_id' => $colleagueEmployee->id,
            'grade_id' => $this->juniorGrade->id,
            'salary_level_id' => null,
            'salary_step_id' => $stepForJunior->id,
        ])
        ->assertSessionHasErrors('salary_step_id');
});
