<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\HrmsCore\Enums\PromotionCategory;
use App\Modules\HrmsCore\Enums\PromotionStatus;
use App\Modules\HrmsCore\Http\Requests\Promotion\ReviewStaffPromotionRequest;
use App\Modules\HrmsCore\Http\Requests\Promotion\StoreStaffPromotionRequest;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Organization\Grade;
use App\Modules\HrmsCore\Models\Promotion\StaffPromotion;
use App\Modules\HrmsCore\Models\Salary\SalaryLevel;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class HrmsPromotionWebController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->activeTenantId($request);
        $user = $request->user();
        $actorEmployee = $this->findActorEmployee($request, $tenantId);

        $canApprove = $user?->can('hrms.promotion.approve') ?? false;
        $canRecommend = $user?->can('hrms.promotion.recommend') ?? false;
        $canRequest = $user?->can('hrms.promotion.request') ?? false;
        $canViewPerm = $user?->can('hrms.promotion.view') ?? false;

        abort_if(
            ! $canApprove
            && ! $canRecommend
            && ! $canRequest
            && ! $canViewPerm
            && $actorEmployee === null,
            403
        );

        $scope = (string) $request->string('scope');
        $statusFilter = (string) $request->string('status');
        $categoryFilter = (string) $request->string('category');
        $requestedEmployeeId = $request->integer('employee_id');

        if ($requestedEmployeeId > 0) {
            $requestedEmployee = Employee::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $requestedEmployeeId)
                ->first();

            abort_if($requestedEmployee === null, 404);

            $isPartyForEmployee = $actorEmployee !== null
                && (
                    $actorEmployee->id === $requestedEmployee->id
                    || StaffPromotion::query()
                        ->where('tenant_id', $tenantId)
                        ->where('employee_id', $requestedEmployee->id)
                        ->where(function (Builder $sub) use ($actorEmployee): void {
                            $sub->where('supervisor_id', $actorEmployee->id)
                                ->orWhere('hr_approver_id', $actorEmployee->id);
                        })
                        ->exists()
                );

            abort_if(! $canApprove && ! $isPartyForEmployee, 403);
        }

        $baseQuery = StaffPromotion::query()->where('tenant_id', $tenantId);
        $this->applyVisibilityScope($baseQuery, $canApprove, $actorEmployee, $scope);

        $promotions = (clone $baseQuery)
            ->with([
                'employee:id,uuid,first_name,last_name,employee_staff_id,grade_id,salary_level_id',
                'fromGrade:id,name',
                'toGrade:id,name',
                'fromSalaryLevel:id,name,base_salary',
                'toSalaryLevel:id,name,base_salary',
                'supervisor:id,first_name,last_name',
                'hrApprover:id,first_name,last_name',
            ])
            ->when($statusFilter !== '', fn ($q) => $q->where('status', $statusFilter))
            ->when($categoryFilter !== '', fn ($q) => $q->where('category', $categoryFilter))
            ->when($requestedEmployeeId > 0, fn ($q) => $q->where('employee_id', $requestedEmployeeId))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $myPromotions = $actorEmployee !== null
            ? StaffPromotion::query()
                ->where('tenant_id', $tenantId)
                ->where('employee_id', $actorEmployee->id)
                ->with(['fromGrade:id,name', 'toGrade:id,name', 'fromSalaryLevel:id,name', 'toSalaryLevel:id,name'])
                ->latest('id')
                ->get()
            : collect();

        $employees = Employee::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'employee_staff_id', 'grade_id', 'salary_level_id']);

        $grades = Grade::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $salaryLevels = SalaryLevel::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'grade_id', 'base_salary']);

        $totalPromotions = (clone $baseQuery)->count();
        $awaitingSupervisorCount = (clone $baseQuery)
            ->where('status', PromotionStatus::AwaitingSupervisorApproval->value)
            ->count();
        $awaitingHrCount = (clone $baseQuery)
            ->where('status', PromotionStatus::AwaitingHrApproval->value)
            ->count();
        $approvedCount = (clone $baseQuery)
            ->where('status', PromotionStatus::Approved->value)
            ->count();

        return view('hrms-core::promotions', [
            'promotions' => $promotions,
            'myPromotions' => $myPromotions,
            'employees' => $employees,
            'grades' => $grades,
            'salaryLevels' => $salaryLevels,
            'categories' => PromotionCategory::cases(),
            'statuses' => PromotionStatus::cases(),
            'actorEmployee' => $actorEmployee,
            'canApprove' => $canApprove,
            'canRecommend' => $canRecommend,
            'canRequest' => $canRequest,
            'scope' => $scope,
            'statusFilter' => $statusFilter,
            'categoryFilter' => $categoryFilter,
            'requestedEmployeeId' => $requestedEmployeeId,
            'totalPromotions' => $totalPromotions,
            'awaitingSupervisorCount' => $awaitingSupervisorCount,
            'awaitingHrCount' => $awaitingHrCount,
            'approvedCount' => $approvedCount,
        ]);
    }

    public function show(Request $request, StaffPromotion $promotion): View
    {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($promotion, $tenantId);

        $user = $request->user();
        $actorEmployee = $this->findActorEmployee($request, $tenantId);
        $canApprove = $user?->can('hrms.promotion.approve') ?? false;
        $canRecommend = $user?->can('hrms.promotion.recommend') ?? false;

        abort_if(! $canApprove && ! $this->isPartyToPromotion($promotion, $actorEmployee), 403);

        $promotion->load([
            'employee.grade',
            'employee.salaryLevel',
            'fromGrade',
            'toGrade',
            'fromSalaryLevel',
            'toSalaryLevel',
            'supervisor',
            'hrApprover',
        ]);

        return view('hrms-core::promotions-show', [
            'promotion' => $promotion,
            'actorEmployee' => $actorEmployee,
            'canApprove' => $canApprove,
            'canRecommend' => $canRecommend,
        ]);
    }

    public function store(StoreStaffPromotionRequest $request): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        $user = $request->user();
        $actorEmployee = $this->findActorEmployee($request, $tenantId);
        $canApprove = $user?->can('hrms.promotion.approve') ?? false;
        $canRecommend = $user?->can('hrms.promotion.recommend') ?? false;

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $employeeId = (int) $validated['employee_id'];

        // Ordinary staff with only hrms.promotion.request (not recommend/approve) may only request for themselves
        if (! $canApprove && ! $canRecommend && ($actorEmployee === null || $actorEmployee->id !== $employeeId)) {
            abort(403);
        }

        $employee = Employee::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($employeeId);

        $toGradeId = isset($validated['to_grade_id']) ? (int) $validated['to_grade_id'] : null;
        $toSalaryLevelId = isset($validated['to_salary_level_id']) ? (int) $validated['to_salary_level_id'] : null;
        $supervisorId = isset($validated['supervisor_id']) ? (int) $validated['supervisor_id'] : null;

        if ($toGradeId === null && $toSalaryLevelId === null) {
            throw ValidationException::withMessages([
                'to_grade_id' => 'Please specify at least a proposed grade or a proposed salary level.',
            ]);
        }

        if ($supervisorId !== null && $supervisorId === $employee->id) {
            throw ValidationException::withMessages([
                'supervisor_id' => 'An employee cannot be assigned as their own reviewing supervisor.',
            ]);
        }

        if ($toSalaryLevelId !== null) {
            $toLevel = SalaryLevel::query()
                ->where('tenant_id', $tenantId)
                ->findOrFail($toSalaryLevelId);

            if ($toLevel->grade_id !== null && $toGradeId !== null && $toLevel->grade_id !== $toGradeId) {
                throw ValidationException::withMessages([
                    'to_salary_level_id' => 'The proposed salary level does not belong to the proposed grade.',
                ]);
            }
        }

        $hasActivePromotion = StaffPromotion::query()
            ->where('tenant_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->whereIn('status', [
                PromotionStatus::Pending->value,
                PromotionStatus::AwaitingSupervisorApproval->value,
                PromotionStatus::AwaitingHrApproval->value,
            ])
            ->exists();

        if ($hasActivePromotion) {
            throw ValidationException::withMessages([
                'employee_id' => 'This employee already has an active promotion request in progress.',
            ]);
        }

        $submitForReview = $request->boolean('submit_for_review', true);

        $promotion = StaffPromotion::query()->create([
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'category' => (string) $validated['category'],
            'status' => $submitForReview
                ? PromotionStatus::AwaitingSupervisorApproval->value
                : PromotionStatus::Pending->value,
            'from_grade_id' => $employee->grade_id,
            'to_grade_id' => $toGradeId,
            'from_salary_level_id' => $employee->salary_level_id,
            'to_salary_level_id' => $toSalaryLevelId,
            'supervisor_id' => $supervisorId,
            'effective_date' => isset($validated['effective_date']) ? (string) $validated['effective_date'] : null,
            'requested_date' => isset($validated['requested_date'])
                ? (string) $validated['requested_date']
                : Carbon::today()->toDateString(),
            'reason' => (string) $validated['reason'],
            'justification' => isset($validated['justification']) ? (string) $validated['justification'] : null,
        ]);

        return redirect()
            ->route('hrms-core.promotions.show', $promotion)
            ->with('status', 'Staff promotion request created.');
    }

    public function submitForReview(Request $request, StaffPromotion $promotion): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($promotion, $tenantId);

        $user = $request->user();
        $actorEmployee = $this->findActorEmployee($request, $tenantId);
        $canApprove = $user?->can('hrms.promotion.approve') ?? false;
        $canRecommend = $user?->can('hrms.promotion.recommend') ?? false;

        abort_if(
            ! $canApprove
            && ! $canRecommend
            && ($actorEmployee === null || $actorEmployee->id !== $promotion->employee_id),
            403
        );

        if (! $promotion->submitForSupervisorReview()) {
            throw ValidationException::withMessages([
                'status' => "Cannot submit promotion from {$promotion->status->label()} to supervisor review.",
            ]);
        }

        return back()->with('status', 'Promotion submitted for supervisor review.');
    }

    public function reviewBySupervisor(
        ReviewStaffPromotionRequest $request,
        StaffPromotion $promotion
    ): RedirectResponse {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($promotion, $tenantId);

        $user = $request->user();
        $actorEmployee = $this->findActorEmployee($request, $tenantId);
        $canApprove = $user?->can('hrms.promotion.approve') ?? false;
        $canRecommend = $user?->can('hrms.promotion.recommend') ?? false;

        $isAssignedSupervisor = $actorEmployee !== null
            && $actorEmployee->id !== $promotion->employee_id
            && ($promotion->supervisor_id === $actorEmployee->id || $promotion->supervisor_id === null)
            && $canRecommend;

        if (! $isAssignedSupervisor) {
            abort_if(! $canApprove, 403);
        }

        if ($promotion->status !== PromotionStatus::AwaitingSupervisorApproval) {
            throw ValidationException::withMessages([
                'status' => 'Only promotions awaiting supervisor approval can be reviewed at this stage.',
            ]);
        }

        $reviewerEmployeeId = $this->requireActorEmployeeId($request, $tenantId);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $decision = (string) $validated['decision'];
        $comments = isset($validated['comments']) ? (string) $validated['comments'] : null;
        $rejectionReason = isset($validated['rejection_reason']) ? (string) $validated['rejection_reason'] : $comments;

        if (! $isAssignedSupervisor) {
            activity('hrms-promotion')
                ->performedOn($promotion)
                ->causedBy($user)
                ->withProperties([
                    'stage' => 'supervisor_review',
                    'decision' => $decision,
                    'actor_employee_id' => $reviewerEmployeeId,
                ])
                ->event('promotion.supervisor_review.override')
                ->log('HR approver override on supervisor promotion review');
        }

        if ($decision === 'approve') {
            $promotion->approveBySupervisor($reviewerEmployeeId, $comments);

            return back()->with('status', 'Supervisor recommendation recorded. Awaiting HR approval.');
        }

        $promotion->rejectBySupervisor($reviewerEmployeeId, $rejectionReason);

        return back()->with('status', 'Promotion request rejected at supervisor stage.');
    }

    public function reviewByHr(
        ReviewStaffPromotionRequest $request,
        StaffPromotion $promotion
    ): RedirectResponse {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($promotion, $tenantId);

        $user = $request->user();
        abort_if(! ($user?->can('hrms.promotion.approve') ?? false), 403);

        if ($promotion->status !== PromotionStatus::AwaitingHrApproval) {
            throw ValidationException::withMessages([
                'status' => 'Only promotions awaiting HR approval can be finalized at this stage.',
            ]);
        }

        $hrApproverId = $this->requireActorEmployeeId($request, $tenantId);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $decision = (string) $validated['decision'];
        $comments = isset($validated['comments']) ? (string) $validated['comments'] : null;
        $rejectionReason = isset($validated['rejection_reason']) ? (string) $validated['rejection_reason'] : ($comments ?? 'Rejected by HR');

        if ($decision === 'approve') {
            DB::connection('tenant')->transaction(function () use ($promotion, $hrApproverId, $comments): void {
                $promotion->approveByHr($hrApproverId, $comments);

                // Synchronize the employee's grade and salary level upon final HR approval
                $employee = $promotion->employee()->first();
                if ($employee !== null) {
                    $updates = [];
                    if ($promotion->to_grade_id !== null) {
                        $updates['grade_id'] = $promotion->to_grade_id;
                    }
                    if ($promotion->to_salary_level_id !== null) {
                        $updates['salary_level_id'] = $promotion->to_salary_level_id;
                    }
                    if ($updates !== []) {
                        $employee->update($updates);
                    }
                }
            });

            return back()->with('status', 'Promotion approved by HR and employee grade/salary level updated.');
        }

        $promotion->rejectByHr($hrApproverId, $rejectionReason, $comments);

        return back()->with('status', 'Promotion rejected by HR.');
    }

    public function destroy(Request $request, StaffPromotion $promotion): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($promotion, $tenantId);

        $user = $request->user();
        $actorEmployee = $this->findActorEmployee($request, $tenantId);
        $canApprove = $user?->can('hrms.promotion.approve') ?? false;

        abort_if(
            ! $canApprove
            && ($actorEmployee === null || $actorEmployee->id !== $promotion->employee_id),
            403
        );

        if ($promotion->status !== PromotionStatus::Pending) {
            throw ValidationException::withMessages([
                'promotion' => 'Only pending promotion requests can be deleted.',
            ]);
        }

        $promotion->delete();

        return redirect()
            ->route('hrms-core.promotions.index')
            ->with('status', 'Promotion request deleted.');
    }

    /**
     * @param  Builder<StaffPromotion>  $query
     */
    private function applyVisibilityScope(
        Builder $query,
        bool $canApprove,
        ?Employee $actorEmployee,
        string $scope
    ): void {
        if ($scope === 'mine') {
            $query->where('employee_id', $actorEmployee !== null ? $actorEmployee->id : -1);

            return;
        }

        if ($canApprove) {
            return;
        }

        if ($actorEmployee === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $actorId = $actorEmployee->id;
        $query->where(function (Builder $sub) use ($actorId): void {
            $sub->where('employee_id', $actorId)
                ->orWhere('supervisor_id', $actorId)
                ->orWhere('hr_approver_id', $actorId);
        });
    }

    private function isPartyToPromotion(StaffPromotion $promotion, ?Employee $actorEmployee): bool
    {
        if ($actorEmployee === null) {
            return false;
        }

        return in_array($actorEmployee->id, [
            $promotion->employee_id,
            $promotion->supervisor_id,
            $promotion->hr_approver_id,
        ], true);
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

    private function requireActorEmployeeId(Request $request, string $tenantId): int
    {
        $employee = $this->findActorEmployee($request, $tenantId);
        if ($employee === null) {
            throw ValidationException::withMessages([
                'employee' => 'Your user account is not linked to an employee profile in this organization.',
            ]);
        }

        return $employee->id;
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
