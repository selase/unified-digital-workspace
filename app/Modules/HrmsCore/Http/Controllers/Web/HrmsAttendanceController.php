<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\HrmsCore\Http\Requests\Attendance\ManualAttendanceRequest;
use App\Modules\HrmsCore\Models\Attendance\AttendanceRecord;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Services\AttendanceService;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

final class HrmsAttendanceController extends Controller
{
    public function index(Request $request, AttendanceService $attendance): View
    {
        abort_unless($request->user()?->can('hrms.attendance.view'), 403);
        $tenantId = $this->tenantId($request);
        $me = $this->currentEmployee($request, $tenantId);
        $canManage = (bool) $request->user()?->can('hrms.attendance.manage');

        $recent = $me === null ? collect() : AttendanceRecord::query()
            ->where('tenant_id', $tenantId)->where('employee_id', $me->id)
            ->where('work_date', '>=', today()->subDays(13)->toDateString())
            ->orderByDesc('clock_in_at')->get()
            ->groupBy(fn (AttendanceRecord $record): string => $record->work_date->toDateString());

        $day = $this->parseDay((string) $request->query('date'));
        $team = $canManage
            ? AttendanceRecord::query()->where('tenant_id', $tenantId)->whereDate('work_date', $day->toDateString())
                ->with('employee:id,first_name,last_name,employee_staff_id')->orderBy('clock_in_at')->get()
            : collect();

        return view('hrms-core::attendance', [
            'me' => $me,
            'openSession' => $me === null ? null : $attendance->openSession($me),
            'todayMinutes' => $me === null ? 0 : $attendance->minutesOn($me, today()),
            'recent' => $recent,
            'canManage' => $canManage,
            'day' => $day,
            'team' => $team,
            'employees' => $canManage ? Employee::query()->where('tenant_id', $tenantId)->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_staff_id']) : collect(),
        ]);
    }

    public function clockIn(Request $request, AttendanceService $attendance): RedirectResponse
    {
        abort_unless($request->user()?->can('hrms.attendance.view'), 403);
        $me = $this->currentEmployee($request, $this->tenantId($request));

        if (! $me instanceof Employee) {
            return back()->with('error', 'Your account is not linked to an employee record. Ask HR to link it.');
        }

        try {
            $attendance->clockIn($me, mb_substr((string) $request->input('note', ''), 0, 255) ?: null, $this->actor($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Clocked in.');
    }

    public function clockOut(Request $request, AttendanceService $attendance): RedirectResponse
    {
        abort_unless($request->user()?->can('hrms.attendance.view'), 403);
        $me = $this->currentEmployee($request, $this->tenantId($request));

        if (! $me instanceof Employee) {
            return back()->with('error', 'Your account is not linked to an employee record. Ask HR to link it.');
        }

        try {
            $attendance->clockOut($me);
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Clocked out.');
    }

    public function storeManual(ManualAttendanceRequest $request, AttendanceService $attendance): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $employee = Employee::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->validated('employee_id'));

        try {
            $attendance->recordManual($employee, Carbon::parse($request->validated('clock_in_at')), Carbon::parse($request->validated('clock_out_at')), $request->validated('note'), $this->actor($request));
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Attendance recorded.');
    }

    public function correct(ManualAttendanceRequest $request, AttendanceService $attendance, string $record): RedirectResponse
    {
        $model = AttendanceRecord::query()->where('tenant_id', $this->tenantId($request))->where('uuid', $record)->firstOrFail();

        try {
            $attendance->correct($model, Carbon::parse($request->validated('clock_in_at')), Carbon::parse($request->validated('clock_out_at')), $request->validated('note'));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Attendance corrected.');
    }

    private function parseDay(string $value): CarbonInterface
    {
        try {
            return $value !== '' ? Carbon::parse($value)->startOfDay() : today();
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

    private function actor(Request $request): ?string
    {
        $uuid = $request->user()?->uuid;

        return $uuid ? (string) $uuid : null;
    }
}
