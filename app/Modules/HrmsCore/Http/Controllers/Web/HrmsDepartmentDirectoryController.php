<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Organization\Directorate;
use App\Modules\HrmsCore\Models\Organization\OrgPermissionAssignment;
use App\Modules\HrmsCore\Models\Organization\OrgRoleAssignment;
use App\Modules\HrmsCore\Models\Organization\Unit;
use App\Services\Tenancy\EntitlementService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class HrmsDepartmentDirectoryController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext, EntitlementService $entitlementService): View
    {
        abort_if(! $request->user()?->can('hrms.departments.view'), 403);

        $search = mb_trim((string) $request->string('search'));
        $status = (string) $request->string('status');
        $tenantId = (string) $tenantContext->activeTenantId();

        $directorates = Directorate::query()
            ->with(['head:id,first_name,last_name,title', 'deputy:id,first_name,last_name,title', 'roleAssignments', 'permissionAssignments'])
            ->withCount(['departments', 'units', 'employees'])
            ->orderBy('name')
            ->get();

        $departments = Department::query()
            ->with(['directorate:id,name', 'head:id,first_name,last_name,title', 'deputy:id,first_name,last_name,title', 'roleAssignments', 'permissionAssignments'])
            ->withCount(['departmentTypes', 'units', 'employees'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when(in_array($status, ['active', 'inactive'], true), function ($query) use ($status): void {
                $query->where('is_active', $status === 'active');
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $units = Unit::query()
            ->with(['department:id,name', 'directorate:id,name', 'head:id,first_name,last_name,title', 'deputy:id,first_name,last_name,title', 'roleAssignments', 'permissionAssignments'])
            ->withCount('employees')
            ->orderBy('name')
            ->get();

        $employees = Employee::query()
            ->where('is_active', true)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'title', 'employee_staff_id']);

        $availableRoles = $tenantId !== ''
            ? Role::query()
                ->where('name', '!=', 'Superadmin')
                ->where(function ($q) use ($tenantId): void {
                    $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                })
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        $allowedPermissions = $tenantId !== ''
            ? $entitlementService->getAllowedPermissionsForTenant($tenantId)
            : [];

        return view('hrms-core::departments', [
            'directorates' => $directorates,
            'departments' => $departments,
            'units' => $units,
            'employees' => $employees,
            'availableRoles' => $availableRoles,
            'allowedPermissions' => $allowedPermissions,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function storeDirectorate(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'head_employee_id' => ['nullable', 'integer'],
            'deputy_employee_id' => ['nullable', 'integer'],
        ]);

        [$headId, $deputyId] = $this->validateLeadershipEmployees(
            isset($validated['head_employee_id']) ? (int) $validated['head_employee_id'] : null,
            isset($validated['deputy_employee_id']) ? (int) $validated['deputy_employee_id'] : null,
        );

        Directorate::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'head_employee_id' => $headId,
            'deputy_employee_id' => $deputyId,
            'is_active' => true,
        ]);

        return redirect()
            ->route('hrms-core.departments.index')
            ->with('status', 'success')
            ->with('message', 'Directorate created successfully.');
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'directorate_id' => ['nullable', 'integer'],
            'head_employee_id' => ['nullable', 'integer'],
            'deputy_employee_id' => ['nullable', 'integer'],
        ]);

        $directorateId = null;
        if (! empty($validated['directorate_id'])) {
            $directorateId = Directorate::query()->findOrFail((int) $validated['directorate_id'])->id;
        }

        [$headId, $deputyId] = $this->validateLeadershipEmployees(
            isset($validated['head_employee_id']) ? (int) $validated['head_employee_id'] : null,
            isset($validated['deputy_employee_id']) ? (int) $validated['deputy_employee_id'] : null,
        );

        Department::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'directorate_id' => $directorateId,
            'head_employee_id' => $headId,
            'deputy_employee_id' => $deputyId,
            'is_active' => true,
        ]);

        return redirect()
            ->route('hrms-core.departments.index')
            ->with('status', 'success')
            ->with('message', 'Department created successfully.');
    }

    public function storeUnit(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'department_id' => ['nullable', 'integer'],
            'directorate_id' => ['nullable', 'integer'],
            'head_employee_id' => ['nullable', 'integer'],
            'deputy_employee_id' => ['nullable', 'integer'],
        ]);

        $departmentId = null;
        if (! empty($validated['department_id'])) {
            $departmentId = Department::query()->findOrFail((int) $validated['department_id'])->id;
        }

        $directorateId = null;
        if (! empty($validated['directorate_id'])) {
            $directorateId = Directorate::query()->findOrFail((int) $validated['directorate_id'])->id;
        }

        [$headId, $deputyId] = $this->validateLeadershipEmployees(
            isset($validated['head_employee_id']) ? (int) $validated['head_employee_id'] : null,
            isset($validated['deputy_employee_id']) ? (int) $validated['deputy_employee_id'] : null,
        );

        Unit::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'department_id' => $departmentId,
            'directorate_id' => $directorateId,
            'head_employee_id' => $headId,
            'deputy_employee_id' => $deputyId,
            'is_active' => true,
        ]);

        return redirect()
            ->route('hrms-core.departments.index')
            ->with('status', 'success')
            ->with('message', 'Unit created successfully.');
    }

    public function updateLeadership(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'org_type' => ['required', 'string', 'in:directorate,department,unit'],
            'org_id' => ['required', 'integer'],
            'head_employee_id' => ['nullable', 'integer'],
            'deputy_employee_id' => ['nullable', 'integer'],
        ]);

        $node = $this->resolveOrgNode($validated['org_type'], (int) $validated['org_id']);

        [$headId, $deputyId] = $this->validateLeadershipEmployees(
            isset($validated['head_employee_id']) ? (int) $validated['head_employee_id'] : null,
            isset($validated['deputy_employee_id']) ? (int) $validated['deputy_employee_id'] : null,
        );

        $node->update([
            'head_employee_id' => $headId,
            'deputy_employee_id' => $deputyId,
        ]);

        return redirect()
            ->route('hrms-core.departments.index')
            ->with('status', 'success')
            ->with('message', 'Organizational leadership updated.');
    }

    public function assignRbac(Request $request, TenantContext $tenantContext, EntitlementService $entitlementService): RedirectResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'org_type' => ['required', 'string', 'in:directorate,department,unit'],
            'org_id' => ['required', 'integer'],
            'role_name' => ['nullable', 'string', 'max:160'],
            'permission_name' => ['nullable', 'string', 'max:160'],
        ]);

        $tenantId = (string) $tenantContext->activeTenantId();
        $node = $this->resolveOrgNode($validated['org_type'], (int) $validated['org_id']);
        $actorUuid = (string) $request->user()?->uuid;

        if (! empty($validated['role_name'])) {
            $roleName = (string) $validated['role_name'];
            if ($roleName === 'Superadmin') {
                throw ValidationException::withMessages([
                    'role_name' => ['Cannot assign Landlord Superadmin role to a tenant organizational unit.'],
                ]);
            }

            $role = Role::query()
                ->where('name', $roleName)
                ->where(function ($q) use ($tenantId): void {
                    $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                })
                ->first();

            if (! $role) {
                throw ValidationException::withMessages([
                    'role_name' => ['The selected role does not exist in this tenant.'],
                ]);
            }

            OrgRoleAssignment::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'assignable_type' => $node::class,
                    'assignable_id' => $node->getKey(),
                    'role_name' => $role->name,
                ],
                [
                    'role_id' => $role->id,
                    'assigned_by_id' => $actorUuid,
                ]
            );
        }

        if (! empty($validated['permission_name'])) {
            $permissionName = (string) $validated['permission_name'];
            $allowed = $entitlementService->getAllowedPermissionsForTenant($tenantId);

            if (! in_array($permissionName, $allowed, true)) {
                throw ValidationException::withMessages([
                    'permission_name' => ['This tenant is not entitled to the requested permission or its module is disabled.'],
                ]);
            }

            OrgPermissionAssignment::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'assignable_type' => $node::class,
                    'assignable_id' => $node->getKey(),
                    'permission_name' => $permissionName,
                ],
                [
                    'assigned_by_id' => $actorUuid,
                ]
            );
        }

        return redirect()
            ->route('hrms-core.departments.index')
            ->with('status', 'success')
            ->with('message', 'Organizational authorization granted.');
    }

    public function revokeRbac(Request $request, TenantContext $tenantContext): RedirectResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'assignment_type' => ['required', 'string', 'in:role,permission'],
            'assignment_id' => ['required', 'integer'],
        ]);

        $tenantId = (string) $tenantContext->activeTenantId();

        if ($validated['assignment_type'] === 'role') {
            $assignment = OrgRoleAssignment::query()
                ->where('tenant_id', $tenantId)
                ->findOrFail((int) $validated['assignment_id']);
            $assignment->delete();
        } else {
            $assignment = OrgPermissionAssignment::query()
                ->where('tenant_id', $tenantId)
                ->findOrFail((int) $validated['assignment_id']);
            $assignment->delete();
        }

        return redirect()
            ->route('hrms-core.departments.index')
            ->with('status', 'success')
            ->with('message', 'Organizational authorization revoked.');
    }

    private function authorizeManage(Request $request): void
    {
        $user = $request->user();
        abort_if(
            ! $user
            || (
                ! $user->can('hrms.departments.manage')
                && ! $user->can('hrms.departments.create')
                && ! $user->can('hrms.departments.update')
                && ! $user->can('core.roles.update')
            ),
            403
        );
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function validateLeadershipEmployees(?int $headId, ?int $deputyId): array
    {
        if ($headId !== null && $headId === $deputyId) {
            throw ValidationException::withMessages([
                'deputy_employee_id' => ['An employee cannot be both the Head and Deputy of the same organizational unit.'],
            ]);
        }

        if ($headId !== null) {
            $headId = Employee::query()->findOrFail($headId)->id;
        }

        if ($deputyId !== null) {
            $deputyId = Employee::query()->findOrFail($deputyId)->id;
        }

        return [$headId, $deputyId];
    }

    private function resolveOrgNode(string $orgType, int $orgId): Model
    {
        return match ($orgType) {
            'directorate' => Directorate::query()->findOrFail($orgId),
            'department' => Department::query()->findOrFail($orgId),
            'unit' => Unit::query()->findOrFail($orgId),
            default => abort(422),
        };
    }
}
