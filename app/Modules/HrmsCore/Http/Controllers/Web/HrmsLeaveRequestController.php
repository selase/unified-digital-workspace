<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\HrmsCore\Enums\LeaveStatus;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Leave\LeaveCategory;
use App\Modules\HrmsCore\Models\Leave\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class HrmsLeaveRequestController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('hrms.leave.view'), 403);

        $search = mb_trim((string) $request->string('search'));
        $status = (string) $request->string('status');

        $leaveRequests = LeaveRequest::query()
            ->with(['employee:id,first_name,last_name,employee_staff_id', 'leaveCategory:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('employee', function ($employeeQuery) use ($search): void {
                    $employeeQuery
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_staff_id', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $metrics = [
            'total' => LeaveRequest::query()->count(),
            'pending' => LeaveRequest::query()->where('status', LeaveStatus::Pending)->count(),
            'approved' => LeaveRequest::query()->where('status', LeaveStatus::Approved)->count(),
            'rejected' => LeaveRequest::query()->where('status', LeaveStatus::Rejected)->count(),
        ];

        return view('hrms-core::leave-requests', [
            'leaveRequests' => $leaveRequests,
            'search' => $search,
            'status' => $status,
            'metrics' => $metrics,
        ]);
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()?->can('hrms.leave.request') && ! $request->user()?->can('hrms.leave.view'), 403);

        $employees = Employee::query()
            ->where('is_active', true)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'employee_staff_id']);

        $leaveCategories = LeaveCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('hrms-core::leave-requests-create', [
            'employees' => $employees,
            'leaveCategories' => $leaveCategories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(! $request->user()?->can('hrms.leave.request') && ! $request->user()?->can('hrms.leave.view'), 403);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:hrms_employees,id'],
            'leave_category_id' => ['required', 'integer', 'exists:hrms_leave_categories,id'],
            'proposed_start_date' => ['required', 'date'],
            'proposed_end_date' => ['required', 'date', 'after_or_equal:proposed_start_date'],
            'leave_reasons' => ['nullable', 'string', 'max:2000'],
            'contact_when_away' => ['nullable', 'string', 'max:255'],
            'relieving_officer_id' => ['nullable', 'integer', 'exists:hrms_employees,id'],
        ]);

        $employee = Employee::query()->findOrFail((int) $validated['employee_id']);
        LeaveCategory::query()->findOrFail((int) $validated['leave_category_id']);

        if (! empty($validated['relieving_officer_id'])) {
            Employee::query()->findOrFail((int) $validated['relieving_officer_id']);
        }

        $startDate = Carbon::parse($validated['proposed_start_date']);
        $endDate = Carbon::parse($validated['proposed_end_date']);

        $calculationService = app(\App\Modules\HrmsCore\Services\LeaveCalculationService::class);
        $isHalfDay = $request->boolean('is_half_day');

        // Working days grouped by calendar year (e.g. for leave spanning 31 December)
        $daysByYear = $calculationService->calculateWorkingDaysByYear(
            $startDate,
            $endDate,
            (string) $employee->tenant_id,
            $isHalfDay
        );

        $totalDays = array_sum($daysByYear);
        if ($totalDays <= 0) {
            $totalDays = (float) ($startDate->diffInDays($endDate) + 1);
            $daysByYear = [(int) $startDate->year => $totalDays];
        }

        // Validate sufficient balance across each spanned year before mutating state
        foreach ($daysByYear as $year => $daysForYear) {
            if ($daysForYear <= 0) {
                continue;
            }

            $balance = \App\Modules\HrmsCore\Models\Leave\LeaveBalance::getOrCreateForEmployee(
                $employee->id,
                (int) $validated['leave_category_id'],
                $year
            );

            if (! $balance->hasEnoughDays($daysForYear)) {
                return back()->withErrors([
                    'leave_category_id' => "Insufficient leave balance for {$year}. You have {$balance->remaining_days} day(s) remaining, but requested {$daysForYear} day(s).",
                ])->withInput();
            }
        }

        $createdLeave = \Illuminate\Support\Facades\DB::connection('tenant')->transaction(function () use (
            $daysByYear,
            $employee,
            $validated,
            $totalDays,
            $startDate
        ): LeaveRequest {
            // Atomically add pending days to balances of each spanned year
            foreach ($daysByYear as $year => $daysForYear) {
                if ($daysForYear <= 0) {
                    continue;
                }

                $balance = \App\Modules\HrmsCore\Models\Leave\LeaveBalance::getOrCreateForEmployee(
                    $employee->id,
                    (int) $validated['leave_category_id'],
                    $year
                );
                $balance->addPendingDays($daysForYear);
            }

            $approvers = app(\App\Modules\HrmsCore\Services\OrgHierarchyResolver::class)
                ->resolveApproversForLeave($employee, $startDate);

            return LeaveRequest::create([
                'employee_id' => $employee->id,
                'leave_category_id' => $validated['leave_category_id'],
                'proposed_start_date' => $validated['proposed_start_date'],
                'proposed_end_date' => $validated['proposed_end_date'],
                'no_requested_days' => $totalDays,
                'leave_reasons' => $validated['leave_reasons'] ?? null,
                'contact_when_away' => $validated['contact_when_away'] ?? null,
                'relieving_officer_id' => $validated['relieving_officer_id'] ?? null,
                'supervisor_id' => $approvers['supervisor']?->id,
                'hod_id' => $approvers['hod']?->id,
                'status' => LeaveStatus::Pending,
            ]);
        });

        // Send queued notification to supervisor or HOD
        $approvers = app(\App\Modules\HrmsCore\Services\OrgHierarchyResolver::class)
            ->resolveApproversForLeave($employee, $startDate);
        $targetApprover = $approvers['supervisor'] ?? $approvers['hod'];
        if ($targetApprover && $targetApprover->user) {
            $targetApprover->user->notify(new \App\Notifications\LeaveApprovalRequiredNotification($createdLeave));
        }

        // Emit domain event for workflow automation
        \App\Modules\WorkflowAutomation\Events\WorkflowDomainEvent::dispatch(
            (string) $employee->tenant_id,
            'leave.submitted',
            [
                'leave_request_id' => $createdLeave->id,
                'employee_id' => $employee->id,
                'employee_name' => $employee->displayName(),
                'days' => $totalDays,
                'status' => 'pending',
            ]
        );

        return redirect()
            ->route('hrms-core.leave-requests.index')
            ->with('status', 'success')
            ->with('message', 'Leave request submitted successfully for approval.');
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        abort_if(! $request->user()?->can('hrms.leave.approve'), 403);

        $comments = $request->string('comments', 'Leave approved')->toString();
        $startDate = Carbon::parse($leaveRequest->proposed_start_date);
        $endDate = Carbon::parse($leaveRequest->proposed_end_date);
        $calculationService = app(\App\Modules\HrmsCore\Services\LeaveCalculationService::class);
        $isHalfDay = ((float) $leaveRequest->no_requested_days === 0.5) && $startDate->isSameDay($endDate);

        $daysByYear = $calculationService->calculateWorkingDaysByYear(
            $startDate,
            $endDate,
            (string) $leaveRequest->tenant_id,
            $isHalfDay
        );

        if (array_sum($daysByYear) <= 0) {
            $daysByYear = [(int) $startDate->year => (float) $leaveRequest->no_requested_days];
        }

        \Illuminate\Support\Facades\DB::connection('tenant')->transaction(function () use ($leaveRequest, $comments, $daysByYear): void {
            $leaveRequest->update([
                'status' => LeaveStatus::Approved,
                'approved_start_date' => $leaveRequest->proposed_start_date,
                'approved_end_date' => $leaveRequest->proposed_end_date,
                'no_of_days_approved' => $leaveRequest->no_requested_days,
                'hod_comments' => $comments,
                'hod_decision_at' => now(),
            ]);

            foreach ($daysByYear as $year => $daysForYear) {
                if ($daysForYear <= 0) {
                    continue;
                }

                $balance = \App\Modules\HrmsCore\Models\Leave\LeaveBalance::getOrCreateForEmployee(
                    $leaveRequest->employee_id,
                    $leaveRequest->leave_category_id,
                    $year
                );
                $balance->convertPendingToUsed($daysForYear);
            }
        });

        \App\Modules\WorkflowAutomation\Events\WorkflowDomainEvent::dispatch(
            (string) $leaveRequest->tenant_id,
            'leave.approved',
            [
                'leave_request_id' => $leaveRequest->id,
                'employee_id' => $leaveRequest->employee_id,
                'status' => 'approved',
            ]
        );

        return redirect()
            ->route('hrms-core.leave-requests.index')
            ->with('status', 'success')
            ->with('message', 'Leave request for '.$leaveRequest->employee?->displayName().' approved.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        abort_if(! $request->user()?->can('hrms.leave.approve'), 403);

        $comments = $request->string('comments', 'Leave rejected')->toString();
        $startDate = Carbon::parse($leaveRequest->proposed_start_date);
        $endDate = Carbon::parse($leaveRequest->proposed_end_date);
        $calculationService = app(\App\Modules\HrmsCore\Services\LeaveCalculationService::class);
        $isHalfDay = ((float) $leaveRequest->no_requested_days === 0.5) && $startDate->isSameDay($endDate);

        $daysByYear = $calculationService->calculateWorkingDaysByYear(
            $startDate,
            $endDate,
            (string) $leaveRequest->tenant_id,
            $isHalfDay
        );

        if (array_sum($daysByYear) <= 0) {
            $daysByYear = [(int) $startDate->year => (float) $leaveRequest->no_requested_days];
        }

        \Illuminate\Support\Facades\DB::connection('tenant')->transaction(function () use ($leaveRequest, $comments, $daysByYear): void {
            $leaveRequest->update([
                'status' => LeaveStatus::Rejected,
                'hod_comments' => $comments,
                'hod_decision_at' => now(),
            ]);

            foreach ($daysByYear as $year => $daysForYear) {
                if ($daysForYear <= 0) {
                    continue;
                }

                $balance = \App\Modules\HrmsCore\Models\Leave\LeaveBalance::getOrCreateForEmployee(
                    $leaveRequest->employee_id,
                    $leaveRequest->leave_category_id,
                    $year
                );
                $balance->removePendingDays($daysForYear);
            }
        });

        return redirect()
            ->route('hrms-core.leave-requests.index')
            ->with('status', 'warning')
            ->with('message', 'Leave request for '.$leaveRequest->employee?->displayName().' rejected.');
    }
}
