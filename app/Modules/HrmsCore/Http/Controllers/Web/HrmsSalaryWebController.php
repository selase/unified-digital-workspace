<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\HrmsCore\Http\Requests\Salary\AllowanceRequest;
use App\Modules\HrmsCore\Http\Requests\Salary\AllowanceTypeRequest;
use App\Modules\HrmsCore\Http\Requests\Salary\AssignEmployeeCompensationRequest;
use App\Modules\HrmsCore\Http\Requests\Salary\EmployeeAllowanceRequest;
use App\Modules\HrmsCore\Http\Requests\Salary\SalaryLevelRequest;
use App\Modules\HrmsCore\Http\Requests\Salary\SalaryStepRequest;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Organization\Grade;
use App\Modules\HrmsCore\Models\Salary\Allowance;
use App\Modules\HrmsCore\Models\Salary\AllowanceType;
use App\Modules\HrmsCore\Models\Salary\EmployeeAllowance;
use App\Modules\HrmsCore\Models\Salary\SalaryLevel;
use App\Modules\HrmsCore\Models\Salary\SalaryStep;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class HrmsSalaryWebController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->activeTenantId($request);
        $user = $request->user();
        $actorEmployee = $this->findActorEmployee($request, $tenantId);

        $canManageSalary = $user?->can('hrms.salary.manage') ?? false;
        $canManageAllowances = $canManageSalary || ($user?->can('hrms.allowances.manage') ?? false);
        $canViewCatalog = $canManageSalary
            || $canManageAllowances
            || ($user?->can('hrms.salary.view') ?? false)
            || ($user?->can('hrms.allowances.view') ?? false);

        abort_if(! $canViewCatalog && $actorEmployee === null, 403);

        $scope = (string) $request->string('scope');
        $requestedEmployeeId = $request->integer('employee_id');

        if ($requestedEmployeeId > 0) {
            $requestedEmployee = Employee::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $requestedEmployeeId)
                ->first();

            abort_if($requestedEmployee === null, 404);
            abort_if(
                ! $canManageSalary
                && ! $canManageAllowances
                && ($actorEmployee === null || $actorEmployee->id !== $requestedEmployee->id),
                403
            );
        }

        $grades = Grade::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $salaryLevels = SalaryLevel::query()
            ->where('tenant_id', $tenantId)
            ->with(['grade:id,name', 'salarySteps' => fn ($q) => $q->orderBy('step_number')])
            ->withCount(['salarySteps', 'employees'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $salarySteps = SalaryStep::query()
            ->where('tenant_id', $tenantId)
            ->with(['salaryLevel:id,name,base_salary', 'grade:id,name'])
            ->withCount('employees')
            ->orderBy('salary_level_id')
            ->orderBy('step_number')
            ->get();

        $allowanceTypes = AllowanceType::query()
            ->where('tenant_id', $tenantId)
            ->withCount('allowances')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $allowances = Allowance::query()
            ->where('tenant_id', $tenantId)
            ->with(['allowanceType:id,name,is_taxable', 'grade:id,name'])
            ->withCount('employeeAllowances')
            ->orderBy('name')
            ->get();

        // Party-based confidentiality: Employee-level compensation & allowances are visible ONLY to HR managers or the employee themselves
        $employeeAllowancesQuery = EmployeeAllowance::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'employee:id,uuid,first_name,last_name,employee_staff_id,grade_id,salary_level_id,salary_step_id',
                'allowance.allowanceType:id,name,is_taxable',
            ]);

        $compensatedEmployeesQuery = Employee::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'grade:id,name',
                'salaryLevel:id,name,base_salary',
                'salaryStep:id,name,step_number,step_increment',
            ]);

        if ($scope === 'mine' || (! $canManageSalary && ! $canManageAllowances)) {
            $ownEmployeeId = $actorEmployee !== null ? $actorEmployee->id : -1;
            $employeeAllowancesQuery->where('employee_id', $ownEmployeeId);
            $compensatedEmployeesQuery->where('id', $ownEmployeeId);
        } elseif ($requestedEmployeeId > 0) {
            $employeeAllowancesQuery->where('employee_id', $requestedEmployeeId);
            $compensatedEmployeesQuery->where('id', $requestedEmployeeId);
        }

        $employeeAllowances = $employeeAllowancesQuery
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $compensatedEmployees = $compensatedEmployeesQuery
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $myCompensation = $actorEmployee !== null
            ? Employee::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $actorEmployee->id)
                ->with([
                    'grade:id,name',
                    'salaryLevel:id,name,base_salary',
                    'salaryStep:id,name,step_number,step_increment',
                    'employeeAllowances.allowance.allowanceType',
                ])
                ->first()
            : null;

        $employees = ($canManageSalary || $canManageAllowances)
            ? Employee::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'employee_staff_id', 'grade_id', 'salary_level_id', 'salary_step_id'])
            : collect();

        return view('hrms-core::salary', [
            'grades' => $grades,
            'salaryLevels' => $salaryLevels,
            'salarySteps' => $salarySteps,
            'allowanceTypes' => $allowanceTypes,
            'allowances' => $allowances,
            'employeeAllowances' => $employeeAllowances,
            'compensatedEmployees' => $compensatedEmployees,
            'myCompensation' => $myCompensation,
            'employees' => $employees,
            'actorEmployee' => $actorEmployee,
            'canManageSalary' => $canManageSalary,
            'canManageAllowances' => $canManageAllowances,
            'canViewCatalog' => $canViewCatalog,
            'scope' => $scope,
            'requestedEmployeeId' => $requestedEmployeeId,
            'frequencyOptions' => Allowance::frequencyOptions(),
        ]);
    }

    public function storeLevel(SalaryLevelRequest $request): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $name = (string) $validated['name'];

        $level = SalaryLevel::query()->create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => $this->uniqueSlug(SalaryLevel::class, $name, 'salary-level', $tenantId),
            'grade_id' => isset($validated['grade_id']) ? (int) $validated['grade_id'] : null,
            'base_salary' => (float) $validated['base_salary'],
            'description' => isset($validated['description']) ? (string) $validated['description'] : null,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => isset($validated['sort_order']) ? (int) $validated['sort_order'] : 0,
        ]);

        activity('hrms-salary')
            ->performedOn($level)
            ->causedBy($request->user())
            ->withProperties([
                'action' => 'created',
                'new' => $level->only(['name', 'slug', 'grade_id', 'base_salary', 'description', 'is_active', 'sort_order']),
            ])
            ->event('salary.level.created')
            ->log('Created salary level');

        return back()->with('status', 'Salary level created.');
    }

    public function updateLevel(SalaryLevelRequest $request, SalaryLevel $level): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($level, $tenantId);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $old = $level->only(['name', 'grade_id', 'base_salary', 'description', 'is_active', 'sort_order']);

        $level->update([
            'name' => (string) $validated['name'],
            'grade_id' => array_key_exists('grade_id', $validated) && $validated['grade_id'] !== null
                ? (int) $validated['grade_id']
                : null,
            'base_salary' => (float) $validated['base_salary'],
            'description' => isset($validated['description']) ? (string) $validated['description'] : null,
            'is_active' => $request->boolean('is_active', $level->is_active),
            'sort_order' => isset($validated['sort_order']) ? (int) $validated['sort_order'] : $level->sort_order,
        ]);

        $new = $level->fresh()?->only(['name', 'grade_id', 'base_salary', 'description', 'is_active', 'sort_order']) ?? [];

        activity('hrms-salary')
            ->performedOn($level)
            ->causedBy($request->user())
            ->withProperties(['old' => $old, 'new' => $new])
            ->event('salary.level.updated')
            ->log('Updated salary level');

        return back()->with('status', 'Salary level updated.');
    }

    public function destroyLevel(Request $request, SalaryLevel $level): RedirectResponse
    {
        abort_if(! ($request->user()?->can('hrms.salary.manage') ?? false), 403);

        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($level, $tenantId);

        $old = $level->only(['name', 'slug', 'grade_id', 'base_salary']);
        $level->delete();

        activity('hrms-salary')
            ->performedOn($level)
            ->causedBy($request->user())
            ->withProperties(['deleted' => $old])
            ->event('salary.level.deleted')
            ->log('Deleted salary level');

        return back()->with('status', 'Salary level deleted.');
    }

    public function storeStep(SalaryStepRequest $request): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $name = (string) $validated['name'];

        $salaryLevel = SalaryLevel::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail((int) $validated['salary_level_id']);

        $stepGradeId = isset($validated['grade_id']) ? (int) $validated['grade_id'] : $salaryLevel->grade_id;
        if ($salaryLevel->grade_id !== null && $stepGradeId !== null && $stepGradeId !== $salaryLevel->grade_id) {
            throw ValidationException::withMessages([
                'grade_id' => 'The selected grade does not match the grade linked to the salary level.',
            ]);
        }

        $step = SalaryStep::query()->create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => $this->uniqueSlug(SalaryStep::class, "{$salaryLevel->name}-{$name}", 'salary-step', $tenantId),
            'salary_level_id' => $salaryLevel->id,
            'grade_id' => $stepGradeId,
            'step_number' => (int) $validated['step_number'],
            'step_increment' => (float) $validated['step_increment'],
            'description' => isset($validated['description']) ? (string) $validated['description'] : null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        activity('hrms-salary')
            ->performedOn($step)
            ->causedBy($request->user())
            ->withProperties([
                'action' => 'created',
                'new' => $step->only(['name', 'slug', 'salary_level_id', 'grade_id', 'step_number', 'step_increment', 'description', 'is_active']),
            ])
            ->event('salary.step.created')
            ->log('Created salary step');

        return back()->with('status', 'Salary step created.');
    }

    public function updateStep(SalaryStepRequest $request, SalaryStep $step): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($step, $tenantId);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $salaryLevel = SalaryLevel::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail((int) $validated['salary_level_id']);

        $stepGradeId = isset($validated['grade_id']) ? (int) $validated['grade_id'] : $salaryLevel->grade_id;
        if ($salaryLevel->grade_id !== null && $stepGradeId !== null && $stepGradeId !== $salaryLevel->grade_id) {
            throw ValidationException::withMessages([
                'grade_id' => 'The selected grade does not match the grade linked to the salary level.',
            ]);
        }

        $old = $step->only(['name', 'salary_level_id', 'grade_id', 'step_number', 'step_increment', 'description', 'is_active']);

        $step->update([
            'name' => (string) $validated['name'],
            'salary_level_id' => $salaryLevel->id,
            'grade_id' => $stepGradeId,
            'step_number' => (int) $validated['step_number'],
            'step_increment' => (float) $validated['step_increment'],
            'description' => isset($validated['description']) ? (string) $validated['description'] : null,
            'is_active' => $request->boolean('is_active', $step->is_active),
        ]);

        $new = $step->fresh()?->only(['name', 'salary_level_id', 'grade_id', 'step_number', 'step_increment', 'description', 'is_active']) ?? [];

        activity('hrms-salary')
            ->performedOn($step)
            ->causedBy($request->user())
            ->withProperties(['old' => $old, 'new' => $new])
            ->event('salary.step.updated')
            ->log('Updated salary step');

        return back()->with('status', 'Salary step updated.');
    }

    public function destroyStep(Request $request, SalaryStep $step): RedirectResponse
    {
        abort_if(! ($request->user()?->can('hrms.salary.manage') ?? false), 403);

        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($step, $tenantId);

        $old = $step->only(['name', 'slug', 'salary_level_id', 'step_number', 'step_increment']);
        $step->delete();

        activity('hrms-salary')
            ->performedOn($step)
            ->causedBy($request->user())
            ->withProperties(['deleted' => $old])
            ->event('salary.step.deleted')
            ->log('Deleted salary step');

        return back()->with('status', 'Salary step deleted.');
    }

    public function storeAllowanceType(AllowanceTypeRequest $request): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $name = (string) $validated['name'];

        $allowanceType = AllowanceType::query()->create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => $this->uniqueSlug(AllowanceType::class, $name, 'allowance-type', $tenantId),
            'description' => isset($validated['description']) ? (string) $validated['description'] : null,
            'is_taxable' => $request->boolean('is_taxable', true),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => isset($validated['sort_order']) ? (int) $validated['sort_order'] : 0,
        ]);

        activity('hrms-salary')
            ->performedOn($allowanceType)
            ->causedBy($request->user())
            ->withProperties([
                'action' => 'created',
                'new' => $allowanceType->only(['name', 'slug', 'description', 'is_taxable', 'is_active', 'sort_order']),
            ])
            ->event('salary.allowance_type.created')
            ->log('Created allowance type');

        return back()->with('status', 'Allowance category created.');
    }

    public function updateAllowanceType(AllowanceTypeRequest $request, AllowanceType $allowanceType): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($allowanceType, $tenantId);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $old = $allowanceType->only(['name', 'description', 'is_taxable', 'is_active', 'sort_order']);

        $allowanceType->update([
            'name' => (string) $validated['name'],
            'description' => isset($validated['description']) ? (string) $validated['description'] : null,
            'is_taxable' => $request->boolean('is_taxable', $allowanceType->is_taxable),
            'is_active' => $request->boolean('is_active', $allowanceType->is_active),
            'sort_order' => isset($validated['sort_order']) ? (int) $validated['sort_order'] : $allowanceType->sort_order,
        ]);

        $new = $allowanceType->fresh()?->only(['name', 'description', 'is_taxable', 'is_active', 'sort_order']) ?? [];

        activity('hrms-salary')
            ->performedOn($allowanceType)
            ->causedBy($request->user())
            ->withProperties(['old' => $old, 'new' => $new])
            ->event('salary.allowance_type.updated')
            ->log('Updated allowance type');

        return back()->with('status', 'Allowance category updated.');
    }

    public function destroyAllowanceType(Request $request, AllowanceType $allowanceType): RedirectResponse
    {
        $user = $request->user();
        abort_if(
            ! ($user?->can('hrms.allowances.manage') ?? false)
            && ! ($user?->can('hrms.salary.manage') ?? false),
            403
        );

        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($allowanceType, $tenantId);

        $old = $allowanceType->only(['name', 'slug']);
        $allowanceType->delete();

        activity('hrms-salary')
            ->performedOn($allowanceType)
            ->causedBy($user)
            ->withProperties(['deleted' => $old])
            ->event('salary.allowance_type.deleted')
            ->log('Deleted allowance type');

        return back()->with('status', 'Allowance category deleted.');
    }

    public function storeAllowance(AllowanceRequest $request): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $name = (string) $validated['name'];

        $allowance = Allowance::query()->create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => $this->uniqueSlug(Allowance::class, $name, 'allowance', $tenantId),
            'allowance_type_id' => (int) $validated['allowance_type_id'],
            'grade_id' => isset($validated['grade_id']) ? (int) $validated['grade_id'] : null,
            'amount' => (float) $validated['amount'],
            'frequency' => (string) $validated['frequency'],
            'description' => isset($validated['description']) ? (string) $validated['description'] : null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        activity('hrms-salary')
            ->performedOn($allowance)
            ->causedBy($request->user())
            ->withProperties([
                'action' => 'created',
                'new' => $allowance->only(['name', 'slug', 'allowance_type_id', 'grade_id', 'amount', 'frequency', 'description', 'is_active']),
            ])
            ->event('salary.allowance.created')
            ->log('Created allowance definition');

        return back()->with('status', 'Allowance definition created.');
    }

    public function updateAllowance(AllowanceRequest $request, Allowance $allowance): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($allowance, $tenantId);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $old = $allowance->only(['name', 'allowance_type_id', 'grade_id', 'amount', 'frequency', 'description', 'is_active']);

        $allowance->update([
            'name' => (string) $validated['name'],
            'allowance_type_id' => (int) $validated['allowance_type_id'],
            'grade_id' => array_key_exists('grade_id', $validated) && $validated['grade_id'] !== null
                ? (int) $validated['grade_id']
                : null,
            'amount' => (float) $validated['amount'],
            'frequency' => (string) $validated['frequency'],
            'description' => isset($validated['description']) ? (string) $validated['description'] : null,
            'is_active' => $request->boolean('is_active', $allowance->is_active),
        ]);

        $new = $allowance->fresh()?->only(['name', 'allowance_type_id', 'grade_id', 'amount', 'frequency', 'description', 'is_active']) ?? [];

        activity('hrms-salary')
            ->performedOn($allowance)
            ->causedBy($request->user())
            ->withProperties(['old' => $old, 'new' => $new])
            ->event('salary.allowance.updated')
            ->log('Updated allowance definition');

        return back()->with('status', 'Allowance definition updated.');
    }

    public function destroyAllowance(Request $request, Allowance $allowance): RedirectResponse
    {
        $user = $request->user();
        abort_if(
            ! ($user?->can('hrms.allowances.manage') ?? false)
            && ! ($user?->can('hrms.salary.manage') ?? false),
            403
        );

        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($allowance, $tenantId);

        $old = $allowance->only(['name', 'slug', 'amount']);
        $allowance->delete();

        activity('hrms-salary')
            ->performedOn($allowance)
            ->causedBy($user)
            ->withProperties(['deleted' => $old])
            ->event('salary.allowance.deleted')
            ->log('Deleted allowance definition');

        return back()->with('status', 'Allowance definition deleted.');
    }

    public function assignEmployeeCompensation(AssignEmployeeCompensationRequest $request): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $employee = Employee::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail((int) $validated['employee_id']);

        $targetGradeId = array_key_exists('grade_id', $validated) && $validated['grade_id'] !== null
            ? (int) $validated['grade_id']
            : $employee->grade_id;
        $salaryLevelId = isset($validated['salary_level_id']) ? (int) $validated['salary_level_id'] : null;
        $salaryStepId = isset($validated['salary_step_id']) ? (int) $validated['salary_step_id'] : null;

        if ($salaryLevelId !== null) {
            $level = SalaryLevel::query()
                ->where('tenant_id', $tenantId)
                ->findOrFail($salaryLevelId);

            if ($level->grade_id !== null && $targetGradeId !== null && $level->grade_id !== $targetGradeId) {
                throw ValidationException::withMessages([
                    'salary_level_id' => 'The selected salary level does not belong to the employee grade.',
                ]);
            }
        }

        if ($salaryStepId !== null) {
            $step = SalaryStep::query()
                ->where('tenant_id', $tenantId)
                ->findOrFail($salaryStepId);

            if ($step->salary_level_id !== null && $step->salary_level_id !== $salaryLevelId) {
                throw ValidationException::withMessages([
                    'salary_step_id' => 'The selected salary step does not belong to the selected salary level.',
                ]);
            }
        }

        $old = [
            'grade_id' => $employee->grade_id,
            'salary_level_id' => $employee->salary_level_id,
            'salary_step_id' => $employee->salary_step_id,
        ];

        $employee->update([
            'grade_id' => $targetGradeId,
            'salary_level_id' => $salaryLevelId,
            'salary_step_id' => $salaryStepId,
        ]);

        $new = [
            'grade_id' => $targetGradeId,
            'salary_level_id' => $salaryLevelId,
            'salary_step_id' => $salaryStepId,
        ];

        activity('hrms-salary')
            ->performedOn($employee)
            ->causedBy($request->user())
            ->withProperties([
                'employee_id' => $employee->id,
                'old' => $old,
                'new' => $new,
            ])
            ->event('salary.compensation.updated')
            ->log('Updated employee compensation');

        return back()->with('status', 'Employee salary structure updated.');
    }

    public function storeEmployeeAllowance(EmployeeAllowanceRequest $request): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $employeeAllowance = EmployeeAllowance::query()->create([
            'tenant_id' => $tenantId,
            'employee_id' => (int) $validated['employee_id'],
            'allowance_id' => (int) $validated['allowance_id'],
            'amount' => isset($validated['amount']) && $validated['amount'] !== '' ? (float) $validated['amount'] : null,
            'effective_from' => (string) $validated['effective_from'],
            'effective_to' => isset($validated['effective_to']) && $validated['effective_to'] !== '' ? (string) $validated['effective_to'] : null,
            'is_active' => $request->boolean('is_active', true),
            'notes' => isset($validated['notes']) ? (string) $validated['notes'] : null,
        ]);

        activity('hrms-salary')
            ->performedOn($employeeAllowance)
            ->causedBy($request->user())
            ->withProperties([
                'action' => 'created',
                'employee_id' => $employeeAllowance->employee_id,
                'new' => $employeeAllowance->only(['allowance_id', 'amount', 'effective_from', 'effective_to', 'is_active', 'notes']),
            ])
            ->event('salary.employee_allowance.created')
            ->log('Assigned allowance to employee');

        return back()->with('status', 'Allowance assigned to employee.');
    }

    public function updateEmployeeAllowance(
        EmployeeAllowanceRequest $request,
        EmployeeAllowance $employeeAllowance
    ): RedirectResponse {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($employeeAllowance, $tenantId);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $old = $employeeAllowance->only(['allowance_id', 'amount', 'effective_from', 'effective_to', 'is_active', 'notes']);

        $employeeAllowance->update([
            'allowance_id' => isset($validated['allowance_id']) ? (int) $validated['allowance_id'] : $employeeAllowance->allowance_id,
            'amount' => array_key_exists('amount', $validated) && $validated['amount'] !== null && $validated['amount'] !== ''
                ? (float) $validated['amount']
                : null,
            'effective_from' => (string) $validated['effective_from'],
            'effective_to' => array_key_exists('effective_to', $validated) && $validated['effective_to'] !== ''
                ? $validated['effective_to']
                : null,
            'is_active' => $request->boolean('is_active', $employeeAllowance->is_active),
            'notes' => array_key_exists('notes', $validated) ? $validated['notes'] : $employeeAllowance->notes,
        ]);

        $new = $employeeAllowance->fresh()?->only(['allowance_id', 'amount', 'effective_from', 'effective_to', 'is_active', 'notes']) ?? [];

        activity('hrms-salary')
            ->performedOn($employeeAllowance)
            ->causedBy($request->user())
            ->withProperties([
                'employee_id' => $employeeAllowance->employee_id,
                'old' => $old,
                'new' => $new,
            ])
            ->event('salary.employee_allowance.updated')
            ->log('Updated employee allowance');

        return back()->with('status', 'Employee allowance updated.');
    }

    public function destroyEmployeeAllowance(
        Request $request,
        EmployeeAllowance $employeeAllowance
    ): RedirectResponse {
        $user = $request->user();
        abort_if(
            ! ($user?->can('hrms.allowances.manage') ?? false)
            && ! ($user?->can('hrms.salary.manage') ?? false),
            403
        );

        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($employeeAllowance, $tenantId);

        $old = $employeeAllowance->only(['employee_id', 'allowance_id', 'amount', 'effective_from', 'effective_to']);
        $employeeAllowance->delete();

        activity('hrms-salary')
            ->performedOn($employeeAllowance)
            ->causedBy($user)
            ->withProperties([
                'employee_id' => $employeeAllowance->employee_id,
                'deleted' => $old,
            ])
            ->event('salary.employee_allowance.deleted')
            ->log('Removed employee allowance');

        return back()->with('status', 'Employee allowance removed.');
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function uniqueSlug(string $modelClass, string $name, string $fallback, string $tenantId): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = $fallback;
        }

        if (! $modelClass::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('slug', $base)->exists()) {
            return $base;
        }

        for ($counter = 2; $counter <= 50; $counter++) {
            $candidate = "{$base}-{$counter}";
            if (! $modelClass::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('slug', $candidate)->exists()) {
                return $candidate;
            }
        }

        return $base.'-'.Str::lower(Str::random(8));
    }

    private function findActorEmployee(Request $request, string $tenantId): ?Employee
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        return Employee::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id);
                if ($user->email !== '') {
                    $query->orWhere('email', $user->email);
                }
            })
            ->first();
    }

    private function assertTenantModel(Model $model, string $tenantId): void
    {
        abort_if((string) $model->getAttribute('tenant_id') !== $tenantId, 404);
    }

    private function activeTenantId(Request $request): string
    {
        $tenantId = (string) (session('active_tenant_id') ?? $request->user()?->tenant_id);
        abort_if($tenantId === '', 403);

        return $tenantId;
    }
}
