<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\HrmsCore\Enums\LeaveStatus;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Leave\LeaveCategory;
use App\Modules\HrmsCore\Models\Leave\LeaveRequest;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Organization\Directorate;
use App\Modules\HrmsCore\Models\Organization\OrgPermissionAssignment;
use App\Modules\HrmsCore\Models\Organization\OrgRoleAssignment;
use App\Modules\HrmsCore\Models\Organization\Unit;
use App\Modules\HrmsCore\Services\OrgHierarchyResolver;
use App\Services\Auth\OrgPermissionResolver;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Hierarchy Test Hospital',
        'slug' => 'hierarchy-hospital',
        'isolation_mode' => 'shared',
    ]);

    app(TenantContext::class)->setTenant($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        'hrms.departments.view',
        'hrms.departments.manage',
        'hrms.leave.view',
        'hrms.leave.request',
        'hrms.leave.approve',
        'memos.view',
        'projects.view',
        'admin.tenants.delete',
    ] as $perm) {
        Permission::firstOrCreate(
            ['name' => $perm, 'guard_name' => 'web'],
            ['category' => 'HRMS']
        );
    }

    app(ModuleManager::class)->enableForTenant('hrms-core', $this->tenant);
    app(ModuleManager::class)->enableForTenant('memos', $this->tenant);
});

it('resolves a 3-tier hierarchy chain (Unit -> Department -> Directorate) and fails over to Deputy when Head is on approved leave', function (): void {
    $dirHead = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Grace', 'last_name' => 'Director']);
    $deptHead = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Kofi', 'last_name' => 'HOD']);
    $unitHead = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Ama', 'last_name' => 'UnitHead']);
    $unitDeputy = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Yaw', 'last_name' => 'UnitDeputy']);
    $staff = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Esi', 'last_name' => 'Officer']);

    $directorate = Directorate::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Medical Affairs Directorate',
        'head_employee_id' => $dirHead->id,
        'is_active' => true,
    ]);

    $department = Department::create([
        'tenant_id' => $this->tenant->id,
        'directorate_id' => $directorate->id,
        'name' => 'Internal Medicine Department',
        'head_employee_id' => $deptHead->id,
        'is_active' => true,
    ]);

    $unit = Unit::create([
        'tenant_id' => $this->tenant->id,
        'department_id' => $department->id,
        'name' => 'Cardiology Care Unit',
        'head_employee_id' => $unitHead->id,
        'deputy_employee_id' => $unitDeputy->id,
        'is_active' => true,
    ]);

    $staff->units()->attach($unit->id);

    $resolver = app(OrgHierarchyResolver::class);
    $targetDate = Carbon::parse('2026-11-10');

    // 1. When Unit Head is available: Supervisor is Unit Head, HOD is Department Head, 3rd in chain is Directorate Head
    $initialApprovers = $resolver->resolveApproversForLeave($staff, $targetDate);
    expect($initialApprovers['supervisor']?->id)->toBe($unitHead->id)
        ->and($initialApprovers['hod']?->id)->toBe($deptHead->id)
        ->and($initialApprovers['chain'])->toHaveCount(3)
        ->and($initialApprovers['chain'][0]['role'])->toBe('head')
        ->and($initialApprovers['chain'][2]['employee']->id)->toBe($dirHead->id);

    // 2. Put Unit Head on Approved Leave covering 2026-11-10 -> automatic failover to Unit Deputy!
    $category = LeaveCategory::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Annual Leave',
        'default_days' => 21,
        'is_active' => true,
    ]);

    LeaveRequest::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $unitHead->id,
        'leave_category_id' => $category->id,
        'proposed_start_date' => '2026-11-01',
        'proposed_end_date' => '2026-11-20',
        'approved_start_date' => '2026-11-01',
        'approved_end_date' => '2026-11-20',
        'no_requested_days' => 20,
        'status' => LeaveStatus::Approved,
        'is_recalled' => false,
    ]);

    $failoverApprovers = $resolver->resolveApproversForLeave($staff, $targetDate);
    expect($failoverApprovers['supervisor']?->id)->toBe($unitDeputy->id)
        ->and($failoverApprovers['chain'][0]['role'])->toBe('deputy')
        ->and($failoverApprovers['hod']?->id)->toBe($deptHead->id);

    // 3. When the Unit Deputy themselves requests leave while Unit Head is away, they cannot approve their own leave;
    // it escalates directly to the Department Head (supervisor) and Directorate Head (hod)
    $unitDeputy->units()->attach($unit->id);
    $deputySelfApprovers = $resolver->resolveApproversForLeave($unitDeputy, $targetDate);
    expect($deputySelfApprovers['supervisor']?->id)->toBe($deptHead->id)
        ->and($deputySelfApprovers['hod']?->id)->toBe($dirHead->id);
});

it('rejects setting the same employee as both Head and Deputy and blocks cross-tenant employee injection', function (): void {
    $admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->tenant->users()->syncWithoutDetaching([$admin->id]);
    $admin->givePermissionTo(['hrms.departments.view', 'hrms.departments.manage']);

    $emp = Employee::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(fn () => Department::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Invalid Self-Deputy Dept',
        'head_employee_id' => $emp->id,
        'deputy_employee_id' => $emp->id,
    ]))->toThrow(InvalidArgumentException::class);

    $otherTenant = Tenant::factory()->create(['slug' => 'other-org', 'isolation_mode' => 'shared']);
    $foreignEmp = Employee::withoutGlobalScopes()->create([
        'uuid' => (string) Illuminate\Support\Str::uuid(),
        'tenant_id' => $otherTenant->id,
        'first_name' => 'Outsider',
        'last_name' => 'Staff',
        'is_active' => true,
    ]);

    $url = 'http://'.$this->tenant->slug.'.unified-digital-workspace.test/hrms-core/departments';
    $this->actingAs($admin)
        ->post($url, [
            'name' => 'Cross Tenant Attempt',
            'head_employee_id' => $foreignEmp->id,
        ])
        ->assertNotFound();
});

it('grants inherited permissions from Unit, Department, and Directorate while enforcing module entitlement boundaries and revoking on transfer', function (): void {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $employee = Employee::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    $directorate = Directorate::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Operations Directorate',
        'is_active' => true,
    ]);

    $department = Department::create([
        'tenant_id' => $this->tenant->id,
        'directorate_id' => $directorate->id,
        'name' => 'Records Department',
        'is_active' => true,
    ]);

    $unit = Unit::create([
        'tenant_id' => $this->tenant->id,
        'department_id' => $department->id,
        'name' => 'Registry Unit',
        'is_active' => true,
    ]);

    $employee->units()->attach($unit->id);

    // Initially user does not have memos.view or hrms.leave.view
    expect($user->can('memos.view'))->toBeFalse()
        ->and($user->can('hrms.leave.view'))->toBeFalse();

    // 1. Grant direct permission 'memos.view' to the parent Department
    OrgPermissionAssignment::create([
        'tenant_id' => $this->tenant->id,
        'assignable_type' => Department::class,
        'assignable_id' => $department->id,
        'permission_name' => 'memos.view',
    ]);

    // 2. Grant a Role with 'hrms.leave.view' to the top-level Directorate
    $dirRole = Role::create([
        'name' => 'Directorate Leave Auditor',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $dirRole->givePermissionTo('hrms.leave.view');

    OrgRoleAssignment::create([
        'tenant_id' => $this->tenant->id,
        'assignable_type' => Directorate::class,
        'assignable_id' => $directorate->id,
        'role_id' => $dirRole->id,
        'role_name' => $dirRole->name,
    ]);

    // User in Unit now inherits BOTH Department permission and Directorate role permission!
    expect($user->can('memos.view'))->toBeTrue()
        ->and($user->can('hrms.leave.view'))->toBeTrue();

    // 3. Adversarial check: Attach 'projects.view' (from disabled module 'project-management') and 'admin.tenants.delete' (landlord permission)
    OrgPermissionAssignment::create([
        'tenant_id' => $this->tenant->id,
        'assignable_type' => Unit::class,
        'assignable_id' => $unit->id,
        'permission_name' => 'projects.view',
    ]);
    OrgPermissionAssignment::create([
        'tenant_id' => $this->tenant->id,
        'assignable_type' => Unit::class,
        'assignable_id' => $unit->id,
        'permission_name' => 'admin.tenants.delete',
    ]);

    // Both MUST be denied because project-management is not enabled for this tenant and admin.* is landlord-only!
    expect($user->can('projects.view'))->toBeFalse()
        ->and($user->can('admin.tenants.delete'))->toBeFalse();

    // 4. Detach employee from Unit and flush cache -> inherited permissions are immediately revoked
    $employee->units()->detach($unit->id);
    OrgPermissionResolver::flushForTenant((string) $this->tenant->id);

    expect($user->can('memos.view'))->toBeFalse()
        ->and($user->can('hrms.leave.view'))->toBeFalse();
});
