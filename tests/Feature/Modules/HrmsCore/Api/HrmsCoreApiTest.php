<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\HrmsCore\Enums\AppraisalCycle;
use App\Modules\HrmsCore\Models\Appraisal\Appraisal;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalPeriod;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalTemplate;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Leave\LeaveCategory;
use App\Modules\HrmsCore\Models\Leave\LeaveRequest;
use App\Modules\HrmsCore\Models\Recruitment\Candidate;
use App\Modules\HrmsCore\Models\Recruitment\JobPosting;
use App\Modules\HrmsCore\Models\Recruitment\JobRequisition;
use App\Services\ModuleManager;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

/**
 * @return array{0: User, 1: Tenant}
 */
function createHrmsApiContext(): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    app(ModuleManager::class)->enableForTenant('hrms-core', $tenant);

    return [$user, $tenant];
}

it('returns paginated employees', function () {
    [$user, $tenant] = createHrmsApiContext();

    Employee::factory()
        ->forTenant($tenant->id)
        ->count(3)
        ->create();

    $response = actingAs($user, 'sanctum')
        ->getJson('/api/hrms-core/v1/employees');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                ['id', 'uuid', 'first_name', 'last_name'],
            ],
            'links',
            'meta',
        ]);
});

it('returns leave requests with related data', function () {
    [$user, $tenant] = createHrmsApiContext();

    $employee = Employee::factory()->forTenant($tenant->id)->create();
    $category = LeaveCategory::factory()->forTenant($tenant->id)->create();

    $request = LeaveRequest::factory()
        ->forEmployee($employee)
        ->forCategory($category)
        ->forTenant($tenant->id)
        ->create();

    $response = actingAs($user, 'sanctum')
        ->getJson('/api/hrms-core/v1/leave-requests');

    $response->assertSuccessful()
        ->assertJsonFragment([
            'id' => $request->id,
            'uuid' => $request->uuid,
        ]);
});

it('returns job postings with requisitions', function () {
    [$user, $tenant] = createHrmsApiContext();

    $requisition = JobRequisition::create([
        'tenant_id' => $tenant->id,
        'title' => 'Software Engineer',
        'employment_type' => 'full_time',
        'vacancies' => 2,
        'status' => 'open',
    ]);

    $posting = JobPosting::create([
        'tenant_id' => $tenant->id,
        'requisition_id' => $requisition->id,
        'title' => 'Software Engineer',
    ]);

    $response = actingAs($user, 'sanctum')
        ->getJson('/api/hrms-core/v1/job-postings');

    $response->assertSuccessful()
        ->assertJsonFragment([
            'id' => $posting->id,
            'uuid' => $posting->uuid,
            'title' => $posting->title,
        ]);
});

it('returns candidates', function () {
    [$user, $tenant] = createHrmsApiContext();

    $candidate = Candidate::create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
    ]);

    $response = actingAs($user, 'sanctum')
        ->getJson('/api/hrms-core/v1/candidates');

    $response->assertSuccessful()
        ->assertJsonFragment([
            'id' => $candidate->id,
            'uuid' => $candidate->uuid,
            'first_name' => $candidate->first_name,
            'last_name' => $candidate->last_name,
        ]);
});

it('returns appraisals with period and template', function () {
    [$user, $tenant] = createHrmsApiContext();

    $employee = Employee::factory()->forTenant($tenant->id)->create([
        'user_id' => $user->id,
        'email' => $user->email,
    ]);

    $period = AppraisalPeriod::create([
        'tenant_id' => $tenant->id,
        'name' => 'Annual 2026',
        'slug' => 'annual-2026',
        'cycle' => AppraisalCycle::Annual,
        'start_date' => now()->startOfYear(),
        'end_date' => now()->endOfYear(),
        'is_active' => true,
    ]);

    $template = AppraisalTemplate::create([
        'tenant_id' => $tenant->id,
        'period_id' => $period->id,
        'name' => 'Default Template',
        'slug' => Str::slug('Default Template'),
        'is_default' => true,
        'is_active' => true,
    ]);

    $appraisal = Appraisal::create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'period_id' => $period->id,
        'template_id' => $template->id,
    ]);

    $response = actingAs($user, 'sanctum')
        ->getJson('/api/hrms-core/v1/appraisals');

    $response->assertSuccessful()
        ->assertJsonFragment([
            'id' => $appraisal->id,
            'uuid' => $appraisal->uuid,
        ]);
});

it('enforces party confidentiality and pre-completion supervisor rating redaction on API v1 appraisals and promotions', function () {
    [$employeeUser, $tenant] = createHrmsApiContext();

    $colleagueUser = User::factory()->create();
    $tenant->users()->attach($colleagueUser->id);

    $employee = Employee::factory()->forTenant($tenant->id)->create([
        'user_id' => $employeeUser->id,
        'email' => $employeeUser->email,
    ]);
    $colleagueEmployee = Employee::factory()->forTenant($tenant->id)->create([
        'user_id' => $colleagueUser->id,
        'email' => $colleagueUser->email,
    ]);
    $supervisorEmployee = Employee::factory()->forTenant($tenant->id)->create();

    $period = AppraisalPeriod::create([
        'tenant_id' => $tenant->id,
        'name' => 'Confidential Cycle 2026',
        'slug' => 'confidential-cycle-2026',
        'cycle' => AppraisalCycle::Annual,
        'start_date' => now()->startOfYear(),
        'end_date' => now()->endOfYear(),
        'is_active' => true,
    ]);

    $template = AppraisalTemplate::create([
        'tenant_id' => $tenant->id,
        'period_id' => $period->id,
        'name' => 'Confidential Template',
        'slug' => 'confidential-template',
        'is_default' => true,
        'is_active' => true,
    ]);

    $section = App\Modules\HrmsCore\Models\Appraisal\AppraisalSection::create([
        'tenant_id' => $tenant->id,
        'template_id' => $template->id,
        'name' => 'Core Competency',
        'weight' => 100,
    ]);

    $criterion = App\Modules\HrmsCore\Models\Appraisal\AppraisalCriterion::create([
        'tenant_id' => $tenant->id,
        'section_id' => $section->id,
        'name' => 'Execution',
        'weight' => 100,
    ]);

    $appraisal = Appraisal::create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'period_id' => $period->id,
        'template_id' => $template->id,
        'status' => App\Modules\HrmsCore\Enums\AppraisalStatus::SupervisorReview,
        'self_overall_score' => 80.0,
        'supervisor_overall_score' => 92.5,
        'final_overall_score' => 92.5,
    ]);

    $responseRow = App\Modules\HrmsCore\Models\Appraisal\AppraisalResponse::create([
        'tenant_id' => $tenant->id,
        'appraisal_id' => $appraisal->id,
        'criterion_id' => $criterion->id,
        'self_rating' => 4,
        'self_comments' => 'My self rating',
        'supervisor_rating' => 5,
        'supervisor_comments' => 'HIDDEN_SUPERVISOR_API_COMMENT',
    ]);

    $reviewRow = App\Modules\HrmsCore\Models\Appraisal\AppraisalReview::create([
        'tenant_id' => $tenant->id,
        'appraisal_id' => $appraisal->id,
        'reviewer_id' => $supervisorEmployee->id,
        'reviewer_type' => App\Modules\HrmsCore\Models\Appraisal\AppraisalReview::TYPE_SUPERVISOR,
        'general_comments' => 'HIDDEN_REVIEW_ROW_API_COMMENT',
    ]);

    $promotion = App\Modules\HrmsCore\Models\Promotion\StaffPromotion::create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'supervisor_id' => $supervisorEmployee->id,
        'category' => App\Modules\HrmsCore\Enums\PromotionCategory::Promotion,
        'status' => App\Modules\HrmsCore\Enums\PromotionStatus::AwaitingSupervisorApproval,
        'reason' => 'PRIVATE_PROMOTION_API_REASON',
    ]);

    // 1. Non-party colleague gets empty list on index and 403 on show for both appraisals and promotions
    actingAs($colleagueUser, 'sanctum')
        ->getJson('/api/hrms-core/v1/appraisals')
        ->assertSuccessful()
        ->assertJsonCount(0, 'data');

    actingAs($colleagueUser, 'sanctum')
        ->getJson("/api/hrms-core/v1/appraisals/{$appraisal->id}")
        ->assertForbidden();

    actingAs($colleagueUser, 'sanctum')
        ->getJson('/api/hrms-core/v1/promotions')
        ->assertSuccessful()
        ->assertJsonCount(0, 'data');

    actingAs($colleagueUser, 'sanctum')
        ->getJson("/api/hrms-core/v1/promotions/{$promotion->id}")
        ->assertForbidden();

    // 2. Appraised employee can read their own incomplete appraisal, but pre-completion supervisor fields and reviews are redacted
    $empAppraisalResp = actingAs($employeeUser, 'sanctum')
        ->getJson("/api/hrms-core/v1/appraisals/{$appraisal->id}")
        ->assertSuccessful();

    expect($empAppraisalResp->json('data.supervisor_overall_score'))->toBeNull()
        ->and($empAppraisalResp->json('data.final_overall_score'))->toBeNull()
        ->and($empAppraisalResp->json('data.reviews'))->toBe([])
        ->and($empAppraisalResp->json('data.responses.0.supervisor_rating'))->toBeNull()
        ->and($empAppraisalResp->json('data.responses.0.supervisor_comments'))->toBeNull();

    actingAs($employeeUser, 'sanctum')
        ->getJson("/api/hrms-core/v1/appraisal-responses/{$responseRow->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.supervisor_rating', null)
        ->assertJsonPath('data.supervisor_comments', null);

    actingAs($employeeUser, 'sanctum')
        ->getJson("/api/hrms-core/v1/appraisal-reviews/{$reviewRow->id}")
        ->assertForbidden();
});

it('protects salary levels and salary steps API endpoints with salary permissions', function () {
    [$userWithoutSalary, $tenant] = createHrmsApiContext();

    $level = App\Modules\HrmsCore\Models\Salary\SalaryLevel::create([
        'tenant_id' => $tenant->id,
        'name' => 'API Test Level',
        'slug' => 'api-test-level',
        'base_salary' => 5000.00,
    ]);

    $step = App\Modules\HrmsCore\Models\Salary\SalaryStep::create([
        'tenant_id' => $tenant->id,
        'name' => 'API Test Step',
        'slug' => 'api-test-step',
        'salary_level_id' => $level->id,
        'step_number' => 1,
        'step_increment' => 250.00,
    ]);

    // 1. User without hrms.salary.view or hrms.salary.manage receives 403 Forbidden
    actingAs($userWithoutSalary, 'sanctum')
        ->getJson('/api/hrms-core/v1/salary-levels')
        ->assertForbidden();

    actingAs($userWithoutSalary, 'sanctum')
        ->getJson("/api/hrms-core/v1/salary-levels/{$level->id}")
        ->assertForbidden();

    actingAs($userWithoutSalary, 'sanctum')
        ->getJson('/api/hrms-core/v1/salary-steps')
        ->assertForbidden();

    actingAs($userWithoutSalary, 'sanctum')
        ->getJson("/api/hrms-core/v1/salary-steps/{$step->id}")
        ->assertForbidden();

    // 2. User with hrms.salary.view can read salary-levels and salary-steps
    $userWithSalary = User::factory()->create();
    $tenant->users()->attach($userWithSalary->id);

    $salaryPerm = App\Models\Permission::firstOrCreate(
        ['name' => 'hrms.salary.view', 'guard_name' => 'web'],
        ['category' => 'hrms-core']
    );
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $salaryRole = App\Models\Role::firstOrCreate([
        'name' => 'Salary Viewer API Role',
        'guard_name' => 'web',
        'tenant_id' => $tenant->id,
    ]);
    $salaryRole->syncPermissions(['hrms.salary.view']);
    $userWithSalary->assignRole($salaryRole);

    actingAs($userWithSalary, 'sanctum')
        ->getJson('/api/hrms-core/v1/salary-levels')
        ->assertSuccessful()
        ->assertJsonFragment([
            'id' => $level->id,
            'name' => 'API Test Level',
        ]);

    actingAs($userWithSalary, 'sanctum')
        ->getJson("/api/hrms-core/v1/salary-levels/{$level->id}")
        ->assertSuccessful()
        ->assertJsonFragment([
            'id' => $level->id,
            'name' => 'API Test Level',
        ]);

    actingAs($userWithSalary, 'sanctum')
        ->getJson('/api/hrms-core/v1/salary-steps')
        ->assertSuccessful()
        ->assertJsonFragment([
            'id' => $step->id,
            'name' => 'API Test Step',
        ]);

    actingAs($userWithSalary, 'sanctum')
        ->getJson("/api/hrms-core/v1/salary-steps/{$step->id}")
        ->assertSuccessful()
        ->assertJsonFragment([
            'id' => $step->id,
            'name' => 'API Test Step',
        ]);
});
