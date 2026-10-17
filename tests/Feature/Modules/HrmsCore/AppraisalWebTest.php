<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\HrmsCore\Enums\AppraisalCycle;
use App\Modules\HrmsCore\Enums\AppraisalStatus;
use App\Modules\HrmsCore\Enums\GoalStatus;
use App\Modules\HrmsCore\Enums\RecommendationType;
use App\Modules\HrmsCore\Models\Appraisal\Appraisal;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalComment;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalCompetency;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalPeriod;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalRatingScale;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalRecommendation;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalReview;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalTemplate;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->hrUser = User::factory()->create();
    $this->tenant = setActiveTenantForTest($this->hrUser);

    Session::put('active_tenant_id', $this->tenant->id);
    setPermissionsTeamId($this->tenant->id);
    app(TenantContext::class)->setTenant($this->tenant);
    app(ModuleManager::class)->enableForTenant('hrms-core', $this->tenant);

    $permissions = [
        'hrms.appraisal.view',
        'hrms.appraisal.create',
        'hrms.appraisal.evaluate',
        'hrms.appraisal.manage',
    ];

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(
            ['name' => $perm, 'guard_name' => 'web'],
            ['category' => 'hrms-core']
        );
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $role = Role::firstOrCreate([
        'name' => 'HR Appraisal Manager',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $role->syncPermissions($permissions);
    $this->hrUser->assignRole($role);

    $this->employee = Employee::factory()->forTenant($this->tenant->id)->create([
        'first_name' => 'Akosua',
        'last_name' => 'Mensah',
    ]);
    $this->supervisor = Employee::factory()->forTenant($this->tenant->id)->create([
        'first_name' => 'Kwame',
        'last_name' => 'Boateng',
        'user_id' => $this->hrUser->id,
        'email' => $this->hrUser->email,
    ]);
    $this->hod = Employee::factory()->forTenant($this->tenant->id)->create([
        'first_name' => 'Efua',
        'last_name' => 'Owusu',
    ]);
});

test('can configure appraisal period with default 1-5 rating scales and default template', function (): void {
    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.appraisals.periods.store'), [
            'name' => 'Annual Performance Cycle 2026',
            'cycle' => AppraisalCycle::Annual->value,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'description' => 'Full year appraisal cycle',
            'is_active' => '1',
            'seed_default_scales' => '1',
            'create_default_template' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $period = AppraisalPeriod::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
    expect($period->name)->toBe('Annual Performance Cycle 2026')
        ->and($period->cycle)->toBe(AppraisalCycle::Annual)
        ->and($period->ratingScales()->count())->toBe(5)
        ->and($period->templates()->count())->toBe(1);

    // Update period
    $this->actingAs($this->hrUser)
        ->put(route('hrms-core.appraisals.periods.update', $period), [
            'name' => 'Annual Performance Cycle 2026 (Updated)',
            'cycle' => AppraisalCycle::Annual->value,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'description' => 'Updated cycle description',
            'is_active' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($period->fresh()?->name)->toBe('Annual Performance Cycle 2026 (Updated)');

    // Upsert custom rating scale level 5
    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.appraisals.rating-scales.store'), [
            'period_id' => $period->id,
            'value' => 5,
            'label' => 'Role Model',
            'description' => 'Top tier institutional contributor',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $scaleFive = AppraisalRatingScale::query()
        ->where('period_id', $period->id)
        ->where('value', 5)
        ->firstOrFail();

    expect($scaleFive->label)->toBe('Role Model');
});

test('can initiate employee appraisal, evaluate competencies and goals, and progress through complete workflow', function (): void {
    $period = AppraisalPeriod::create([
        'tenant_id' => $this->tenant->id,
        'name' => '2026 Annual Review',
        'cycle' => AppraisalCycle::Annual,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
    $template = AppraisalTemplate::create([
        'tenant_id' => $this->tenant->id,
        'period_id' => $period->id,
        'name' => 'Standard Template',
    ]);

    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.appraisals.store'), [
            'employee_id' => $this->employee->id,
            'period_id' => $period->id,
            'template_id' => $template->id,
            'supervisor_id' => $this->supervisor->id,
            'hod_id' => $this->hod->id,
            'hr_reviewer_id' => $this->supervisor->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $appraisal = Appraisal::query()
        ->where('employee_id', $this->employee->id)
        ->where('period_id', $period->id)
        ->firstOrFail();

    expect($appraisal->status)->toBe(AppraisalStatus::Draft);

    // Duplicate appraisal for same employee + period is rejected with 422
    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.appraisals.store'), [
            'employee_id' => $this->employee->id,
            'period_id' => $period->id,
            'template_id' => $template->id,
        ])
        ->assertSessionHasErrors('employee_id');

    // Add 2 competencies and 1 goal
    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.appraisals.competencies.store', $appraisal), [
            'name' => 'Clinical & Operational Excellence',
            'description' => 'Delivers high accuracy',
            'behavioral_indicators' => 'Zero compliance lapses',
            'weight' => 50,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.appraisals.competencies.store', $appraisal), [
            'name' => 'Team Collaboration',
            'weight' => 50,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.appraisals.goals.store', $appraisal), [
            'title' => 'Complete ISO Audit Readiness',
            'target' => '100% SOP coverage',
            'weight' => 100,
            'due_date' => '2026-09-30',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $competencies = $appraisal->competencies()->orderBy('id')->get();
    $goal = $appraisal->goals()->firstOrFail();

    expect($competencies)->toHaveCount(2);

    // Move Draft -> Self Assessment
    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.appraisals.status', $appraisal), [
            'status' => AppraisalStatus::SelfAssessment->value,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($appraisal->fresh()?->status)->toBe(AppraisalStatus::SelfAssessment);

    // Cannot submit Self Assessment -> Supervisor Review before any self rating is entered
    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.appraisals.status', $appraisal), [
            'status' => AppraisalStatus::SupervisorReview->value,
        ])
        ->assertSessionHasErrors('status');

    // Evaluate competencies & goal
    $this->actingAs($this->hrUser)
        ->put(route('hrms-core.appraisals.competencies.update', [$appraisal, $competencies[0]]), [
            'self_rating' => 4,
            'self_evidence' => 'Led Q2 audit preparation',
            'supervisor_rating' => 5,
            'supervisor_evidence' => 'Exceptional leadership',
            'final_rating' => 5,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($this->hrUser)
        ->put(route('hrms-core.appraisals.competencies.update', [$appraisal, $competencies[1]]), [
            'self_rating' => 4,
            'supervisor_rating' => 3,
            'final_rating' => 4,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($this->hrUser)
        ->put(route('hrms-core.appraisals.goals.update', [$appraisal, $goal]), [
            'achievement' => 'All 18 SOPs published',
            'status' => GoalStatus::Completed->value,
            'self_rating' => 4,
            'supervisor_rating' => 4,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $appraisal->refresh();
    expect((float) $appraisal->self_overall_score)->toBe(4.0)
        ->and((float) $appraisal->supervisor_overall_score)->toBe(4.0)
        ->and((float) $appraisal->final_overall_score)->toBe(4.5);

    // Record Supervisor Review, Comment, and Recommendation
    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.appraisals.reviews.store', $appraisal), [
            'reviewer_type' => AppraisalReview::TYPE_SUPERVISOR,
            'overall_rating' => 4.2,
            'strengths' => 'Consistent execution',
            'decision' => AppraisalReview::DECISION_APPROVED,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.appraisals.comments.store', $appraisal), [
            'comment' => 'Recommend leadership track workshop in Q4.',
            'type' => AppraisalComment::TYPE_ACTION_ITEM,
            'is_private' => '0',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.appraisals.recommendations.store', $appraisal), [
            'type' => RecommendationType::Promotion->value,
            'description' => 'Ready for Senior Officer grade',
            'action_plan' => 'Submit to promotion committee',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(AppraisalReview::query()->where('appraisal_id', $appraisal->id)->count())->toBe(1)
        ->and(AppraisalComment::query()->where('appraisal_id', $appraisal->id)->count())->toBe(1)
        ->and(AppraisalRecommendation::query()->where('appraisal_id', $appraisal->id)->count())->toBe(1);

    // Advance SelfAssessment -> SupervisorReview -> HodReview -> HrReview -> Complete
    foreach ([
        AppraisalStatus::SupervisorReview,
        AppraisalStatus::HodReview,
        AppraisalStatus::HrReview,
        AppraisalStatus::Complete,
    ] as $nextStage) {
        $this->actingAs($this->hrUser)
            ->post(route('hrms-core.appraisals.status', $appraisal), [
                'status' => $nextStage->value,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    $appraisal->refresh();
    expect($appraisal->status)->toBe(AppraisalStatus::Complete)
        ->and($appraisal->completed_at)->not->toBeNull();

    // Render index and show pages
    $this->actingAs($this->hrUser)
        ->get(route('hrms-core.appraisals.index'))
        ->assertOk()
        ->assertSee('Akosua Mensah');

    $this->actingAs($this->hrUser)
        ->get(route('hrms-core.appraisals.show', $appraisal))
        ->assertOk()
        ->assertSee('Clinical & Operational Excellence')
        ->assertSee('Complete ISO Audit Readiness');
});

test('rejects invalid appraisal workflow jumps and mutations on completed appraisals', function (): void {
    $period = AppraisalPeriod::create([
        'tenant_id' => $this->tenant->id,
        'name' => '2026 Midyear Review',
        'cycle' => AppraisalCycle::Midyear,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
    ]);
    $template = AppraisalTemplate::create([
        'tenant_id' => $this->tenant->id,
        'period_id' => $period->id,
        'name' => 'Midyear Template',
    ]);
    $appraisal = Appraisal::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'period_id' => $period->id,
        'template_id' => $template->id,
        'status' => AppraisalStatus::Draft,
    ]);

    // Cannot jump directly from Draft to Complete or HodReview
    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.appraisals.status', $appraisal), [
            'status' => AppraisalStatus::Complete->value,
        ])
        ->assertSessionHasErrors('status');

    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.appraisals.status', $appraisal), [
            'status' => AppraisalStatus::HodReview->value,
        ])
        ->assertSessionHasErrors('status');

    expect($appraisal->fresh()?->status)->toBe(AppraisalStatus::Draft);

    // Mark Complete and verify competencies cannot be added and appraisal cannot be reopened or deleted
    $appraisal->update(['status' => AppraisalStatus::Complete, 'completed_at' => now()]);

    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.appraisals.competencies.store', $appraisal), [
            'name' => 'Late Competency',
        ])
        ->assertSessionHasErrors('appraisal');

    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.appraisals.status', $appraisal), [
            'status' => AppraisalStatus::SupervisorReview->value,
        ])
        ->assertSessionHasErrors('status');

    $this->actingAs($this->hrUser)
        ->delete(route('hrms-core.appraisals.destroy', $appraisal))
        ->assertSessionHasErrors('appraisal');
});

test('enforces appraisal permission gates and tenant isolation', function (): void {
    $period = AppraisalPeriod::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Tenant A Period',
        'cycle' => AppraisalCycle::Annual,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
    $template = AppraisalTemplate::create([
        'tenant_id' => $this->tenant->id,
        'period_id' => $period->id,
        'name' => 'Tenant A Template',
    ]);
    $appraisal = Appraisal::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'period_id' => $period->id,
        'template_id' => $template->id,
    ]);
    $competency = AppraisalCompetency::create([
        'tenant_id' => $this->tenant->id,
        'appraisal_id' => $appraisal->id,
        'name' => 'Integrity',
    ]);

    // Unprivileged user gets 403
    $unprivilegedUser = User::factory()->create();
    $this->tenant->users()->attach($unprivilegedUser->id);
    Session::put('active_tenant_id', $this->tenant->id);
    setPermissionsTeamId($this->tenant->id);
    app(TenantContext::class)->setTenant($this->tenant);

    $this->actingAs($unprivilegedUser)
        ->get(route('hrms-core.appraisals.index'))
        ->assertForbidden();

    $this->actingAs($unprivilegedUser)
        ->post(route('hrms-core.appraisals.store'), [
            'employee_id' => $this->employee->id,
            'period_id' => $period->id,
            'template_id' => $template->id,
        ])
        ->assertForbidden();

    // Cross-tenant isolation: Tenant B cannot view or mutate Tenant A's appraisal
    $otherTenant = Tenant::factory()->create([
        'name' => 'Other Appraisal Tenant',
        'slug' => 'other-appraisal-tenant',
    ]);
    app(TenantContext::class)->setTenant($otherTenant);

    $otherEmployee = Employee::factory()->forTenant($otherTenant->id)->create();
    $otherAppraisal = Appraisal::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $otherEmployee->id,
        'period_id' => AppraisalPeriod::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Tenant B Period',
            'cycle' => AppraisalCycle::Annual,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ])->id,
        'template_id' => AppraisalTemplate::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Tenant B Template',
        ])->id,
    ]);

    app(TenantContext::class)->setTenant($this->tenant);

    $this->actingAs($this->hrUser)
        ->get(route('hrms-core.appraisals.show', $otherAppraisal))
        ->assertNotFound();

    // Mismatched parent-child competency route returns 404
    $appraisalTwo = Appraisal::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->supervisor->id,
        'period_id' => $period->id,
        'template_id' => $template->id,
    ]);

    $this->actingAs($this->hrUser)
        ->put(route('hrms-core.appraisals.competencies.update', [$appraisalTwo, $competency]), [
            'self_rating' => 5,
        ])
        ->assertNotFound();

    // Cross-tenant employee_id rejected with 422
    $this->actingAs($this->hrUser)
        ->post(route('hrms-core.appraisals.store'), [
            'employee_id' => $otherEmployee->id,
            'period_id' => $period->id,
            'template_id' => $template->id,
        ])
        ->assertSessionHasErrors('employee_id');
});

test('enforces party-based visibility, My Appraisals scope, and hides supervisor ratings from appraised employee until completion', function (): void {
    $period = AppraisalPeriod::create([
        'tenant_id' => $this->tenant->id,
        'name' => '2026 Confidentiality Cycle',
        'cycle' => AppraisalCycle::Annual,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
    $template = AppraisalTemplate::create([
        'tenant_id' => $this->tenant->id,
        'period_id' => $period->id,
        'name' => 'Confidentiality Template',
    ]);

    // Link employee user
    $employeeUser = User::factory()->create(['email' => 'akosua.mensah@example.com']);
    $this->tenant->users()->attach($employeeUser->id);
    $this->employee->update([
        'user_id' => $employeeUser->id,
        'email' => $employeeUser->email,
    ]);

    // Link a colleague who has hrms.appraisal.view and hrms.appraisal.evaluate
    $colleagueUser = User::factory()->create(['email' => 'colleague.staff@example.com']);
    $this->tenant->users()->attach($colleagueUser->id);
    $colleagueEmployee = Employee::factory()->forTenant($this->tenant->id)->create([
        'first_name' => 'Kofi',
        'last_name' => 'Adjei',
        'user_id' => $colleagueUser->id,
        'email' => $colleagueUser->email,
    ]);

    $staffRole = Role::firstOrCreate([
        'name' => 'Staff Appraisal Viewer',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $staffRole->syncPermissions(['hrms.appraisal.view', 'hrms.appraisal.evaluate']);
    $colleagueUser->assignRole($staffRole);

    $employeeAppraisal = Appraisal::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'period_id' => $period->id,
        'template_id' => $template->id,
        'supervisor_id' => $this->supervisor->id,
        'hod_id' => $this->hod->id,
        'hr_reviewer_id' => $this->supervisor->id,
        'status' => AppraisalStatus::SupervisorReview,
        'self_overall_score' => 4.00,
        'supervisor_overall_score' => 2.50,
        'final_overall_score' => 2.75,
    ]);

    $colleagueAppraisal = Appraisal::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $colleagueEmployee->id,
        'period_id' => $period->id,
        'template_id' => $template->id,
        'supervisor_id' => $this->supervisor->id,
        'status' => AppraisalStatus::SelfAssessment,
    ]);

    AppraisalCompetency::create([
        'tenant_id' => $this->tenant->id,
        'appraisal_id' => $employeeAppraisal->id,
        'name' => 'Confidentiality & Ethics',
        'self_rating' => 4,
        'self_evidence' => 'Employee self reflection note',
        'supervisor_rating' => 2,
        'supervisor_evidence' => 'STRICTLY_CONFIDENTIAL_SUPERVISOR_NOTE',
        'final_rating' => 3,
    ]);

    AppraisalReview::create([
        'tenant_id' => $this->tenant->id,
        'appraisal_id' => $employeeAppraisal->id,
        'reviewer_id' => $this->supervisor->id,
        'reviewer_type' => AppraisalReview::TYPE_SUPERVISOR,
        'overall_rating' => 2.5,
        'strengths' => 'SECRET_STAGE_REVIEW_STRENGTH',
        'decision' => AppraisalReview::DECISION_REVISION_REQUESTED,
        'reviewed_at' => now(),
    ]);

    // 1. Colleague with hrms.appraisal.view + evaluate CANNOT view Akosua's appraisal (403)
    $this->actingAs($colleagueUser)
        ->get(route('hrms-core.appraisals.show', $employeeAppraisal))
        ->assertForbidden();

    // And colleague's index only lists their own appraisal, never Akosua's
    $this->actingAs($colleagueUser)
        ->get(route('hrms-core.appraisals.index'))
        ->assertOk()
        ->assertSee('Kofi Adjei')
        ->assertDontSee('Akosua Mensah');

    // 2. Ordinary employee (even without hrms.appraisal.view) can access My Appraisals (?scope=mine) and their own appraisal
    $this->actingAs($employeeUser)
        ->get(route('hrms-core.appraisals.index', ['scope' => 'mine']))
        ->assertOk()
        ->assertSee('Akosua Mensah')
        ->assertDontSee('Kofi Adjei');

    $this->actingAs($employeeUser)
        ->get(route('hrms-core.appraisals.show', $colleagueAppraisal))
        ->assertForbidden();

    // 3. Before completion, the appraised employee CANNOT see supervisor rating, evidence, score, or stage review
    $this->actingAs($employeeUser)
        ->get(route('hrms-core.appraisals.show', $employeeAppraisal))
        ->assertOk()
        ->assertSee('Employee self reflection note')
        ->assertSee('Hidden until appraisal is completed')
        ->assertDontSee('STRICTLY_CONFIDENTIAL_SUPERVISOR_NOTE')
        ->assertDontSee('SECRET_STAGE_REVIEW_STRENGTH')
        ->assertDontSee('2.50');

    // 4. Once completed, the appraised employee CAN see supervisor rating, evidence, score, and stage review
    $employeeAppraisal->update([
        'status' => AppraisalStatus::Complete,
        'completed_at' => now(),
    ]);

    $this->actingAs($employeeUser)
        ->get(route('hrms-core.appraisals.show', $employeeAppraisal))
        ->assertOk()
        ->assertSee('STRICTLY_CONFIDENTIAL_SUPERVISOR_NOTE')
        ->assertSee('SECRET_STAGE_REVIEW_STRENGTH')
        ->assertSee('2.50');
});

test('enforces role and stage boundaries on self_rating, supervisor_rating, final_rating, actor employee linkage, and logs HR overrides', function (): void {
    $period = AppraisalPeriod::create([
        'tenant_id' => $this->tenant->id,
        'name' => '2026 Role Enforcement Cycle',
        'cycle' => AppraisalCycle::Annual,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
    $template = AppraisalTemplate::create([
        'tenant_id' => $this->tenant->id,
        'period_id' => $period->id,
        'name' => 'Role Enforcement Template',
    ]);

    // Set up distinct users for: appraised employee, supervisor (evaluate only, not HR manage), colleague (evaluate only), and HR manager without employee record
    $employeeUser = User::factory()->create(['email' => 'appraised.employee@example.com']);
    $this->tenant->users()->attach($employeeUser->id);
    $this->employee->update([
        'user_id' => $employeeUser->id,
        'email' => $employeeUser->email,
    ]);

    $supervisorUser = User::factory()->create(['email' => 'line.supervisor@example.com']);
    $this->tenant->users()->attach($supervisorUser->id);
    $lineSupervisor = Employee::factory()->forTenant($this->tenant->id)->create([
        'first_name' => 'Nana',
        'last_name' => 'Supervisor',
        'user_id' => $supervisorUser->id,
        'email' => $supervisorUser->email,
    ]);

    $colleagueUser = User::factory()->create(['email' => 'peer.evaluator@example.com']);
    $this->tenant->users()->attach($colleagueUser->id);
    Employee::factory()->forTenant($this->tenant->id)->create([
        'first_name' => 'Yaw',
        'last_name' => 'Colleague',
        'user_id' => $colleagueUser->id,
        'email' => $colleagueUser->email,
    ]);

    $evaluatorRole = Role::firstOrCreate([
        'name' => 'Line Supervisor Evaluator',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $evaluatorRole->syncPermissions(['hrms.appraisal.view', 'hrms.appraisal.evaluate']);
    $supervisorUser->assignRole($evaluatorRole);
    $colleagueUser->assignRole($evaluatorRole);

    $appraisal = Appraisal::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'period_id' => $period->id,
        'template_id' => $template->id,
        'supervisor_id' => $lineSupervisor->id,
        'hod_id' => $this->hod->id,
        'hr_reviewer_id' => $this->supervisor->id,
        'status' => AppraisalStatus::SelfAssessment,
    ]);

    $competency = AppraisalCompetency::create([
        'tenant_id' => $this->tenant->id,
        'appraisal_id' => $appraisal->id,
        'name' => 'Execution Quality',
        'weight' => 100,
    ]);

    // 1. Colleague with hrms.appraisal.evaluate rating someone else's appraisal -> 403
    $this->actingAs($colleagueUser)
        ->put(route('hrms-core.appraisals.competencies.update', [$appraisal, $competency]), [
            'self_rating' => 3,
        ])
        ->assertForbidden();

    $this->actingAs($colleagueUser)
        ->put(route('hrms-core.appraisals.competencies.update', [$appraisal, $competency]), [
            'supervisor_rating' => 3,
        ])
        ->assertForbidden();

    // 2. Supervisor attempting to write employee's self_rating -> 403
    $this->actingAs($supervisorUser)
        ->put(route('hrms-core.appraisals.competencies.update', [$appraisal, $competency]), [
            'self_rating' => 5,
            'self_evidence' => 'Supervisor trying to spoof self rating',
        ])
        ->assertForbidden();

    expect($competency->fresh()?->self_rating)->toBeNull();

    // 3. Supervisor attempting to write supervisor_rating while still in SelfAssessment -> 403
    $this->actingAs($supervisorUser)
        ->put(route('hrms-core.appraisals.competencies.update', [$appraisal, $competency]), [
            'supervisor_rating' => 4,
        ])
        ->assertForbidden();

    // 4. Appraised employee attempting to write supervisor_rating -> 403
    $this->actingAs($employeeUser)
        ->put(route('hrms-core.appraisals.competencies.update', [$appraisal, $competency]), [
            'supervisor_rating' => 5,
        ])
        ->assertForbidden();

    // 5. Appraised employee CAN write self_rating during SelfAssessment and submit to SupervisorReview
    $this->actingAs($employeeUser)
        ->put(route('hrms-core.appraisals.competencies.update', [$appraisal, $competency]), [
            'self_rating' => 4,
            'self_evidence' => 'Completed all assigned deliverables on schedule.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($competency->fresh()?->self_rating)->toBe(4);

    $this->actingAs($employeeUser)
        ->post(route('hrms-core.appraisals.status', $appraisal), [
            'status' => AppraisalStatus::SupervisorReview->value,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($appraisal->fresh()?->status)->toBe(AppraisalStatus::SupervisorReview);

    // 6. Once in SupervisorReview, appraised employee can no longer overwrite self_rating -> 403
    $this->actingAs($employeeUser)
        ->put(route('hrms-core.appraisals.competencies.update', [$appraisal, $competency]), [
            'self_rating' => 5,
        ])
        ->assertForbidden();

    // 7. In SupervisorReview, supervisor CAN write supervisor_rating (302) but CANNOT write final_rating (403)
    $this->actingAs($supervisorUser)
        ->put(route('hrms-core.appraisals.competencies.update', [$appraisal, $competency]), [
            'supervisor_rating' => 4,
            'supervisor_evidence' => 'Verified deliverables.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($competency->fresh()?->supervisor_rating)->toBe(4);

    $this->actingAs($supervisorUser)
        ->put(route('hrms-core.appraisals.competencies.update', [$appraisal, $competency]), [
            'final_rating' => 5,
        ])
        ->assertForbidden();

    // 8. HR with hrms.appraisal.manage overriding self_rating in SupervisorReview is allowed AND audit-logged
    $this->actingAs($this->hrUser)
        ->put(route('hrms-core.appraisals.competencies.update', [$appraisal, $competency]), [
            'self_rating' => 5,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $overrideLog = Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'hrms-appraisal')
        ->where('subject_type', Appraisal::class)
        ->where('subject_id', $appraisal->id)
        ->where('event', 'appraisal.competency.self_rating.override')
        ->where('causer_id', $this->hrUser->id)
        ->first();

    expect($overrideLog)->not->toBeNull();

    // 9. Acting user with no Employee record in the tenant is rejected with 422 (never attributed to supervisor or employee)
    $unlinkedHrUser = User::factory()->create(['email' => 'unlinked.hr@example.com']);
    $this->tenant->users()->attach($unlinkedHrUser->id);
    $hrRole = Role::query()->where('name', 'HR Appraisal Manager')->where('tenant_id', $this->tenant->id)->firstOrFail();
    $unlinkedHrUser->assignRole($hrRole);

    $this->actingAs($unlinkedHrUser)
        ->post(route('hrms-core.appraisals.comments.store', $appraisal), [
            'comment' => 'Unlinked user comment attempt',
            'type' => AppraisalComment::TYPE_GENERAL,
        ])
        ->assertSessionHasErrors('employee');

    $this->actingAs($unlinkedHrUser)
        ->post(route('hrms-core.appraisals.reviews.store', $appraisal), [
            'reviewer_type' => AppraisalReview::TYPE_HR,
            'decision' => AppraisalReview::DECISION_APPROVED,
        ])
        ->assertSessionHasErrors('employee');

    expect(AppraisalComment::query()->where('appraisal_id', $appraisal->id)->count())->toBe(0)
        ->and(AppraisalReview::query()->where('appraisal_id', $appraisal->id)->count())->toBe(0);
});
