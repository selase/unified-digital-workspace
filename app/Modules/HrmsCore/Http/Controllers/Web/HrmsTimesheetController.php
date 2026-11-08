<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\HrmsCore\Http\Requests\Attendance\ReviewTimesheetRequest;
use App\Modules\HrmsCore\Http\Requests\Attendance\SaveTimesheetRequest;
use App\Modules\HrmsCore\Models\Attendance\Timesheet;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Services\AttendanceService;
use App\Modules\HrmsCore\Services\TimesheetService;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

final class HrmsTimesheetController extends Controller
{
    public function index(Request $request, TimesheetService $timesheets, AttendanceService $attendance): View
    {
        abort_unless($request->user()?->can('hrms.attendance.view'), 403);
        $tenantId = $this->tenantId($request);
        $me = $this->currentEmployee($request, $tenantId);
        $weekStart = $timesheets->weekStart($this->parseDate((string) $request->query('week')));
        $canApprove = (bool) $request->user()?->can('hrms.timesheets.approve');

        $sheet = $me instanceof Employee ? $timesheets->forWeek($me, $weekStart)->load('entries') : null;
        $days = collect(range(0, 6))->map(fn (int $offset): Carbon => Carbon::instance($weekStart)->addDays($offset));

        return view('hrms-core::timesheets', [
            'me' => $me,
            'sheet' => $sheet,
            'weekStart' => $weekStart,
            'days' => $days,
            'entriesByDay' => $sheet?->entries->groupBy(fn ($entry): string => $entry->work_date->toDateString()) ?? collect(),
            'clockedMinutes' => $me instanceof Employee ? $days->mapWithKeys(fn (Carbon $day): array => [$day->toDateString() => $attendance->minutesOn($me, $day)]) : collect(),
            'canApprove' => $canApprove,
            'pending' => $canApprove
                ? Timesheet::query()->where('tenant_id', $tenantId)->where('status', Timesheet::STATUS_SUBMITTED)
                    ->when($me instanceof Employee, fn ($query) => $query->where('employee_id', '!=', $me->id))
                    ->with('employee:id,first_name,last_name,employee_staff_id')->orderBy('submitted_at')->get()
                : collect(),
        ]);
    }

    public function save(SaveTimesheetRequest $request, TimesheetService $timesheets): RedirectResponse
    {
        $me = $this->currentEmployee($request, $this->tenantId($request));
        if (! $me instanceof Employee) {
            return back()->with('error', 'Your account is not linked to an employee record. Ask HR to link it.');
        }

        $sheet = $timesheets->forWeek($me, $this->parseDate((string) $request->input('week')));

        try {
            $timesheets->saveEntries($sheet, array_values($request->validated('entries')));
            if ($request->boolean('submit')) {
                $timesheets->submit($sheet->refresh());
            }
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return back()->with('success', $request->boolean('submit') ? 'Timesheet submitted for approval.' : 'Timesheet saved.');
    }

    public function review(ReviewTimesheetRequest $request, TimesheetService $timesheets, string $timesheet): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $sheet = Timesheet::query()->where('tenant_id', $tenantId)->where('uuid', $timesheet)->with('employee')->firstOrFail();
        $isOwn = $sheet->employee->user_id !== null && (int) $sheet->employee->user_id === (int) $request->user()?->id;
        $reviewer = $request->user()?->uuid ? (string) $request->user()->uuid : null;

        try {
            if ($request->validated('decision') === 'approve') {
                $timesheets->approve($sheet, $reviewer, $isOwn, $request->validated('comment'));
            } else {
                $timesheets->reject($sheet, $reviewer, $isOwn, (string) $request->validated('comment'));
            }
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', $request->validated('decision') === 'approve' ? 'Timesheet approved.' : 'Timesheet returned to the employee.');
    }

    private function parseDate(string $value): CarbonInterface
    {
        try {
            return $value !== '' ? Carbon::parse($value) : today();
        } catch (Throwable) {
            return today();
        }
    }

    private function tenantId(Request $request): string
    {
        $tenantId = app(TenantContext::class)->activeTenantId() ?? $request->session()->get('active_tenant_id');
        abort_if(! is_string($tenantId) || $tenantId === '', 403, 'No active organisation.');

        return $tenantId;
    }

    private function currentEmployee(Request $request, string $tenantId): ?Employee
    {
        return Employee::query()->where('tenant_id', $tenantId)->where('user_id', $request->user()?->id)->first();
    }
}
