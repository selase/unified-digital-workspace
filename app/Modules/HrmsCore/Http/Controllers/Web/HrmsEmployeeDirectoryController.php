<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\HrmsCore\Http\Requests\EmployeeStoreRequest;
use App\Modules\HrmsCore\Http\Requests\EmployeeUpdateRequest;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Organization\Center;
use App\Modules\HrmsCore\Models\Organization\Grade;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class HrmsEmployeeDirectoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('hrms.employees.view'), 403);

        $search = mb_trim((string) $request->string('search'));
        $status = (string) $request->string('status');

        $employees = Employee::query()
            ->with(['grade:id,name', 'center:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('employee_staff_id', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, ['active', 'inactive'], true), function ($query) use ($status): void {
                $query->where('is_active', $status === 'active');
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('hrms-core::employees', [
            'employees' => $employees,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()?->can('hrms.employees.create'), 403);

        return view('hrms-core::employees-create', $this->formOptions());
    }

    public function store(EmployeeStoreRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $payload['is_active'] = $request->boolean('is_active', true);

        $employee = Employee::create($payload);

        return redirect()
            ->route('hrms-core.employees.show', $employee)
            ->with('status', 'success')
            ->with('message', 'Employee profile created successfully.');
    }

    public function show(Request $request, Employee $employee): View
    {
        abort_if(! $request->user()?->can('hrms.employees.view'), 403);

        $employee->load([
            'grade:id,name',
            'center:id,name',
            'departments:id,name',
            'directorates:id,name',
            'units:id,name',
        ]);

        return view('hrms-core::employees-show', [
            'employee' => $employee,
        ]);
    }

    public function edit(Request $request, Employee $employee): View|Factory
    {
        abort_if(! $request->user()?->can('hrms.employees.update'), 403);

        return view('hrms-core::employees-edit', $this->formOptions($employee));
    }

    public function update(EmployeeUpdateRequest $request, Employee $employee): RedirectResponse
    {
        $payload = $request->validated();
        $payload['is_active'] = $request->boolean('is_active', false);

        $employee->fill($payload);
        $employee->save();

        return redirect()
            ->route('hrms-core.employees.show', $employee)
            ->with('status', 'success')
            ->with('message', 'Employee profile updated successfully.');
    }

    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        abort_if(! $request->user()?->can('hrms.employees.delete'), 403);

        $employee->delete();

        return redirect()
            ->route('hrms-core.employees.index')
            ->with('status', 'success')
            ->with('message', 'Employee profile deleted.');
    }

    /**
     * @return array{
     *     employee: Employee|null,
     *     grades: \Illuminate\Database\Eloquent\Collection<int, Grade>,
     *     centers: \Illuminate\Database\Eloquent\Collection<int, Center>
     * }
     */
    private function formOptions(?Employee $employee = null): array
    {
        return [
            'employee' => $employee,
            'grades' => Grade::query()->orderBy('name')->get(['id', 'name']),
            'centers' => Center::query()->orderBy('name')->get(['id', 'name']),
        ];
    }
}
