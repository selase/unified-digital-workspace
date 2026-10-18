<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\HrmsCore\Enums\LeaveStatus;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Leave\LeaveCategory;
use App\Modules\HrmsCore\Models\Leave\LeaveRequest;
use App\Modules\HrmsCore\Models\Organization\Center;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Organization\Grade;
use App\Modules\HrmsCore\Models\Recruitment\JobPosting;
use App\Modules\HrmsCore\Models\Recruitment\JobRequisition;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: User, 1: Tenant}
 */
function createHrmsWebContext(): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = [
        'hrms.employees.view',
        'hrms.employees.create',
        'hrms.employees.update',
        'hrms.employees.delete',
        'hrms.departments.view',
        'hrms.leave.view',
        'hrms.leave.request',
        'hrms.leave.approve',
        'hrms.jobs.view',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'category' => 'hrms-core',
            'guard_name' => 'web',
        ], [
            'uuid' => (string) Str::uuid(),
        ]);
    }

    $user->givePermissionTo($permissions);
    app(ModuleManager::class)->enableForTenant('hrms-core', $tenant);

    return [$user, $tenant];
}

describe('HRMS Core Web Workflows', function (): void {
    test('hrms hub renders dashboard with live institutional workforce metrics', function (): void {
        [$user, $tenant] = createHrmsWebContext();

        $grade = Grade::create(['name' => 'Senior Officer', 'tenant_id' => $tenant->id]);
        $center = Center::create(['name' => 'Main Campus', 'tenant_id' => $tenant->id]);

        $emp1 = Employee::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Kwame',
            'last_name' => 'Mensah',
            'email' => 'kwame.mensah@example.com',
            'employee_staff_id' => 'STF-001',
            'grade_id' => $grade->id,
            'center_id' => $center->id,
            'is_active' => true,
        ]);

        $category = LeaveCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Annual Leave',
            'default_days' => 25,
        ]);

        LeaveRequest::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $emp1->id,
            'leave_category_id' => $category->id,
            'proposed_start_date' => Carbon::now()->addDays(5)->toDateString(),
            'proposed_end_date' => Carbon::now()->addDays(10)->toDateString(),
            'no_requested_days' => 5,
            'status' => LeaveStatus::Pending,
        ]);

        $response = $this->actingAs($user)->get(route('hrms-core.index'));

        $response->assertOk();
        $response->assertSee('People Operations &amp; Talent Hub', false);
        $response->assertSee('Total Staff');
        $response->assertSee('Headcount');
        $response->assertSee('Kwame Mensah');
        $response->assertSee('STF-001');
        $response->assertSee('Annual Leave');
    });

    test('employee directory lists staff with search and status filtering', function (): void {
        [$user, $tenant] = createHrmsWebContext();

        Employee::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Ama',
            'last_name' => 'Osei',
            'email' => 'ama.osei@example.com',
            'employee_staff_id' => 'STF-100',
            'is_active' => true,
        ]);

        Employee::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Kofi',
            'last_name' => 'Annan',
            'email' => 'kofi.annan@example.com',
            'employee_staff_id' => 'STF-200',
            'is_active' => false,
        ]);

        // General list
        $response = $this->actingAs($user)->get(route('hrms-core.employees.index'));
        $response->assertOk();
        $response->assertSee('Ama Osei');
        $response->assertSee('Kofi Annan');

        // Search filter
        $searchResponse = $this->actingAs($user)->get(route('hrms-core.employees.index', ['search' => 'Ama']));
        $searchResponse->assertOk();
        $searchResponse->assertSee('Ama Osei');
        $searchResponse->assertDontSee('Kofi Annan');

        // Status filter
        $statusResponse = $this->actingAs($user)->get(route('hrms-core.employees.index', ['status' => 'inactive']));
        $statusResponse->assertOk();
        $statusResponse->assertSee('Kofi Annan');
        $statusResponse->assertDontSee('Ama Osei');
    });

    test('can create, view, update, and delete an employee profile', function (): void {
        [$user, $tenant] = createHrmsWebContext();

        $grade = Grade::create(['name' => 'Principal Consultant', 'tenant_id' => $tenant->id]);
        $center = Center::create(['name' => 'Accra Medical Wing', 'tenant_id' => $tenant->id]);

        // Create page
        $createPageResponse = $this->actingAs($user)->get(route('hrms-core.employees.create'));
        $createPageResponse->assertOk();
        $createPageResponse->assertSee('Create Employee Profile');

        // Store action
        $storeResponse = $this->actingAs($user)->post(route('hrms-core.employees.store'), [
            'first_name' => 'Abena',
            'last_name' => 'Appiah',
            'employee_staff_id' => 'EMP-777',
            'email' => 'abena.appiah@example.com',
            'mobile' => '+233201234567',
            'grade_id' => $grade->id,
            'center_id' => $center->id,
            'is_active' => '1',
        ]);

        $employee = Employee::where('email', 'abena.appiah@example.com')->first();
        expect($employee)->not->toBeNull();
        expect($employee->first_name)->toBe('Abena');
        expect($employee->employee_staff_id)->toBe('EMP-777');

        $storeResponse->assertRedirect(route('hrms-core.employees.show', $employee));

        // Show page
        $showResponse = $this->actingAs($user)->get(route('hrms-core.employees.show', $employee));
        $showResponse->assertOk();
        $showResponse->assertSee('Abena Appiah');
        $showResponse->assertSee('EMP-777');
        $showResponse->assertSee('Principal Consultant');
        $showResponse->assertSee('Accra Medical Wing');

        // Edit page
        $editResponse = $this->actingAs($user)->get(route('hrms-core.employees.edit', $employee));
        $editResponse->assertOk();
        $editResponse->assertSee('Edit Employee Profile');

        // Update action
        $updateResponse = $this->actingAs($user)->put(route('hrms-core.employees.update', $employee), [
            'first_name' => 'Abena',
            'last_name' => 'Appiah-Boakye',
            'employee_staff_id' => 'EMP-777',
            'email' => 'abena.boakye@example.com',
            'mobile' => '+233201234567',
            'grade_id' => $grade->id,
            'center_id' => $center->id,
            'is_active' => '1',
        ]);

        $updateResponse->assertRedirect(route('hrms-core.employees.show', $employee));
        $employee->refresh();
        expect($employee->last_name)->toBe('Appiah-Boakye');
        expect($employee->email)->toBe('abena.boakye@example.com');

        // Delete action
        $deleteResponse = $this->actingAs($user)->delete(route('hrms-core.employees.destroy', $employee));
        $deleteResponse->assertRedirect(route('hrms-core.employees.index'));
        expect(Employee::find($employee->id))->toBeNull();
    });

    test('leave requests management handles creation, approval, and rejection', function (): void {
        [$user, $tenant] = createHrmsWebContext();

        $employee = Employee::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Yaw',
            'last_name' => 'Boateng',
            'email' => 'yaw.boateng@example.com',
            'is_active' => true,
        ]);

        $category = LeaveCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Casual Leave',
            'default_days' => 10,
        ]);

        // Create page
        $createResponse = $this->actingAs($user)->get(route('hrms-core.leave-requests.create'));
        $createResponse->assertOk();
        $createResponse->assertSee('Submit Leave Application');

        // Store leave request (Monday through Friday = 5 working days)
        $nextMonday = Carbon::now()->next(Carbon::MONDAY);
        $startDate = $nextMonday->toDateString();
        $endDate = $nextMonday->copy()->addDays(4)->toDateString();

        $storeResponse = $this->actingAs($user)->post(route('hrms-core.leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'proposed_start_date' => $startDate,
            'proposed_end_date' => $endDate,
            'leave_reasons' => 'Family obligation and personal study',
            'contact_when_away' => '+233244112233',
        ]);

        $storeResponse->assertRedirect(route('hrms-core.leave-requests.index'));

        $leaveRequest = LeaveRequest::where('employee_id', $employee->id)->first();
        expect($leaveRequest)->not->toBeNull();
        expect($leaveRequest->status)->toBe(LeaveStatus::Pending);
        expect((float) $leaveRequest->no_requested_days)->toBe(5.0);

        // Index page
        $indexResponse = $this->actingAs($user)->get(route('hrms-core.leave-requests.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('Yaw Boateng');
        $indexResponse->assertSee('Casual Leave');
        $indexResponse->assertSee('Pending');

        // Approve leave
        $approveResponse = $this->actingAs($user)->post(route('hrms-core.leave-requests.approve', $leaveRequest), [
            'comments' => 'Leave application approved by HOD',
        ]);
        $approveResponse->assertRedirect(route('hrms-core.leave-requests.index'));

        $leaveRequest->refresh();
        expect($leaveRequest->status)->toBe(LeaveStatus::Approved);
        expect($leaveRequest->hod_comments)->toBe('Leave application approved by HOD');
        expect($leaveRequest->hod_decision_at)->not->toBeNull();

        // Second request for rejection
        $secondRequest = LeaveRequest::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'proposed_start_date' => Carbon::now()->addDays(20)->toDateString(),
            'proposed_end_date' => Carbon::now()->addDays(22)->toDateString(),
            'no_requested_days' => 3,
            'status' => LeaveStatus::Pending,
        ]);

        $rejectResponse = $this->actingAs($user)->post(route('hrms-core.leave-requests.reject', $secondRequest), [
            'comments' => 'Critical staffing shortage during this period',
        ]);
        $rejectResponse->assertRedirect(route('hrms-core.leave-requests.index'));

        $secondRequest->refresh();
        expect($secondRequest->status)->toBe(LeaveStatus::Rejected);
        expect($secondRequest->hod_comments)->toBe('Critical staffing shortage during this period');
    });

    test('department directory lists institutional units with types count', function (): void {
        [$user, $tenant] = createHrmsWebContext();

        $dept = Department::create([
            'tenant_id' => $tenant->id,
            'name' => 'Clinical Pathology',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('hrms-core.departments.index'));
        $response->assertOk();
        $response->assertSee('Clinical Pathology');
        $response->assertSee('Department Directory');
    });

    test('recruitment pipeline lists job postings and requisition links', function (): void {
        [$user, $tenant] = createHrmsWebContext();

        $dept = Department::create([
            'tenant_id' => $tenant->id,
            'name' => 'Internal Audit',
            'is_active' => true,
        ]);

        $req = JobRequisition::create([
            'tenant_id' => $tenant->id,
            'title' => 'Senior Compliance Auditor',
            'department_id' => $dept->id,
            'requisition_number' => 'REQ-AUDIT-01',
            'status' => 'approved',
            'vacancies' => 2,
        ]);

        JobPosting::create([
            'tenant_id' => $tenant->id,
            'title' => 'Lead Internal Auditor Vacancy',
            'requisition_id' => $req->id,
            'closing_date' => Carbon::now()->addMonth(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('hrms-core.recruitment.index'));
        $response->assertOk();
        $response->assertSee('Lead Internal Auditor Vacancy');
        $response->assertSee('REQ-AUDIT-01');
        $response->assertSee('Internal Audit');
    });

    test('adversarial security tests assert 403 rejection on unauthorized actions', function (): void {
        $unauthorizedUser = User::factory()->create();
        $tenant = setActiveTenantForTest($unauthorizedUser);

        Session::put('active_tenant_id', $tenant->id);
        setPermissionsTeamId($tenant->id);
        app(TenantContext::class)->setTenant($tenant);
        app(ModuleManager::class)->enableForTenant('hrms-core', $tenant);

        // Employee directory view rejection
        $this->actingAs($unauthorizedUser)
            ->get(route('hrms-core.employees.index'))
            ->assertStatus(403);

        // Employee create view rejection
        $this->actingAs($unauthorizedUser)
            ->get(route('hrms-core.employees.create'))
            ->assertStatus(403);

        // Leave requests index rejection
        $this->actingAs($unauthorizedUser)
            ->get(route('hrms-core.leave-requests.index'))
            ->assertStatus(403);

        // Departments index rejection
        $this->actingAs($unauthorizedUser)
            ->get(route('hrms-core.departments.index'))
            ->assertStatus(403);

        // Recruitment index rejection
        $this->actingAs($unauthorizedUser)
            ->get(route('hrms-core.recruitment.index'))
            ->assertStatus(403);

        // Leave approve rejection
        $employee = Employee::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Test',
            'last_name' => 'Subject',
        ]);
        $category = LeaveCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'General Leave',
            'default_days' => 5,
        ]);
        $leaveRequest = LeaveRequest::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'leave_category_id' => $category->id,
            'proposed_start_date' => Carbon::now()->toDateString(),
            'proposed_end_date' => Carbon::now()->addDays(2)->toDateString(),
            'no_requested_days' => 2,
            'status' => LeaveStatus::Pending,
        ]);

        $this->actingAs($unauthorizedUser)
            ->post(route('hrms-core.leave-requests.approve', $leaveRequest))
            ->assertStatus(403);

        $this->actingAs($unauthorizedUser)
            ->post(route('hrms-core.leave-requests.reject', $leaveRequest))
            ->assertStatus(403);
    });
});
