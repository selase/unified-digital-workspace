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

    $employee = Employee::factory()->forTenant($tenant->id)->create();

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
