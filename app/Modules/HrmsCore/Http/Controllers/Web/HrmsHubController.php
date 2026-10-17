<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\HrmsCore\Enums\LeaveStatus;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Leave\LeaveRequest;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Recruitment\JobPosting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class HrmsHubController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('hrms.employees.view'), 403);

        $totalEmployees = Employee::query()->count();
        $activeEmployees = Employee::query()->where('is_active', true)->count();
        $totalDepartments = Department::query()->count();
        $pendingLeaves = LeaveRequest::query()->where('status', LeaveStatus::Pending)->count();
        $activePostings = JobPosting::query()->where('is_active', true)->count();

        $recentEmployees = Employee::query()
            ->with(['grade:id,name', 'center:id,name', 'departments:id,name'])
            ->latest('id')
            ->limit(6)
            ->get();

        $pendingLeaveRequests = LeaveRequest::query()
            ->with(['employee:id,first_name,last_name,employee_staff_id', 'leaveCategory:id,name'])
            ->where('status', LeaveStatus::Pending)
            ->latest('id')
            ->limit(5)
            ->get();

        $departmentsSummary = Department::query()
            ->withCount('employees')
            ->where('is_active', true)
            ->orderByDesc('employees_count')
            ->limit(5)
            ->get();

        return view('hrms-core::index', [
            'totalEmployees' => $totalEmployees,
            'activeEmployees' => $activeEmployees,
            'totalDepartments' => $totalDepartments,
            'pendingLeaves' => $pendingLeaves,
            'activePostings' => $activePostings,
            'recentEmployees' => $recentEmployees,
            'pendingLeaveRequests' => $pendingLeaveRequests,
            'departmentsSummary' => $departmentsSummary,
            'featureCount' => count((array) config('modules.hrms-core.features', [])),
            'permissionCount' => count((array) config('modules.hrms-core.permissions', [])),
            'moduleVersion' => (string) config('modules.hrms-core.version', '1.0.0'),
        ]);
    }
}
