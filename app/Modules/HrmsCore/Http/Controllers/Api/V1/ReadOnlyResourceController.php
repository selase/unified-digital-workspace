<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\HrmsCore\Enums\AppraisalStatus;
use App\Modules\HrmsCore\Models\Appraisal\Appraisal;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalGoal;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalRecommendation;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalResponse;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalReview;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalScore;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Leave\LeaveBalance;
use App\Modules\HrmsCore\Models\Leave\LeaveRequest;
use App\Modules\HrmsCore\Models\Promotion\StaffPromotion;
use App\Modules\HrmsCore\Models\Recruitment\Candidate;
use App\Modules\HrmsCore\Models\Recruitment\CandidateApplication;
use App\Modules\HrmsCore\Models\Recruitment\CandidateAssessment;
use App\Modules\HrmsCore\Models\Recruitment\CandidateDocument;
use App\Modules\HrmsCore\Models\Recruitment\CandidateReference;
use App\Modules\HrmsCore\Models\Recruitment\Interview;
use App\Modules\HrmsCore\Models\Recruitment\InterviewEvaluation;
use App\Modules\HrmsCore\Models\Recruitment\InterviewPanel;
use App\Modules\HrmsCore\Models\Recruitment\InterviewStage;
use App\Modules\HrmsCore\Models\Recruitment\JobOffer;
use App\Modules\HrmsCore\Models\Recruitment\JobPosting;
use App\Modules\HrmsCore\Models\Recruitment\JobRequisition;
use App\Modules\HrmsCore\Models\Recruitment\OfferNegotiation;
use App\Modules\HrmsCore\Models\Recruitment\OnboardingTask;
use App\Modules\HrmsCore\Models\Salary\SalaryLevel;
use App\Modules\HrmsCore\Models\Salary\SalaryStep;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

final class ReadOnlyResourceController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    /**
     * @return AnonymousResourceCollection<JsonResource>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        [$modelClass, $resourceClass, $with, $withCount] = $this->resolveRouteConfig($request);

        $perPage = $this->resolvePerPage($request);

        $query = $modelClass::query()->with($with);

        if ($withCount !== []) {
            $query->withCount($withCount);
        }

        $this->applyConfidentialityScope($request, $modelClass, $query);

        return $resourceClass::collection($query->paginate($perPage));
    }

    public function show(Request $request, int $id): JsonResource
    {
        [$modelClass, $resourceClass, $with, $withCount] = $this->resolveRouteConfig($request);

        $query = $modelClass::query()->with($with);

        if ($withCount !== []) {
            $query->withCount($withCount);
        }

        /** @var Model $record */
        $record = $query->findOrFail($id);

        $this->authorizeRecordVisibility($request, $record);

        return new $resourceClass($record);
    }

    /**
     * @param  class-string  $modelClass
     * @param  Builder<Model>  $query
     */
    private function applyConfidentialityScope(Request $request, string $modelClass, Builder $query): void
    {
        $user = $request->user();

        if (in_array($modelClass, [SalaryLevel::class, SalaryStep::class], true)) {
            abort_if(! $this->canViewSalary($user), 403);

            return;
        }

        if (in_array($modelClass, [
            JobRequisition::class,
            JobPosting::class,
            Candidate::class,
            CandidateApplication::class,
            CandidateDocument::class,
            CandidateReference::class,
            CandidateAssessment::class,
            InterviewStage::class,
            Interview::class,
            InterviewPanel::class,
            InterviewEvaluation::class,
            JobOffer::class,
            OfferNegotiation::class,
            OnboardingTask::class,
        ], true)) {
            if (! $this->canViewRecruitment($user)) {
                $query->whereRaw('1 = 0');
            }

            return;
        }

        if ($modelClass === LeaveRequest::class) {
            if ($this->canManageLeave($user)) {
                return;
            }

            $actorEmployee = $this->findActorEmployee($request);
            if ($actorEmployee === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $actorId = $actorEmployee->id;
            $query->where(function (Builder $sub) use ($actorId): void {
                $sub->where('employee_id', $actorId)
                    ->orWhere('supervisor_id', $actorId)
                    ->orWhere('hod_id', $actorId)
                    ->orWhere('hr_verifier_id', $actorId)
                    ->orWhere('relieving_officer_id', $actorId);
            });

            return;
        }

        if ($modelClass === LeaveBalance::class) {
            if ($this->canManageLeave($user)) {
                return;
            }

            $actorEmployee = $this->findActorEmployee($request);
            if ($actorEmployee === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where('employee_id', $actorEmployee->id);

            return;
        }

        if ($modelClass === Appraisal::class) {
            if ($user?->can('hrms.appraisal.manage') ?? false) {
                return;
            }

            $actorEmployee = $this->findActorEmployee($request);
            $this->scopeAppraisalQuery($query, $actorEmployee);

            return;
        }

        if (in_array($modelClass, [
            AppraisalResponse::class,
            AppraisalGoal::class,
            AppraisalScore::class,
            AppraisalRecommendation::class,
        ], true)) {
            if ($user?->can('hrms.appraisal.manage') ?? false) {
                return;
            }

            $actorEmployee = $this->findActorEmployee($request);
            $query->whereHas('appraisal', function (Builder $appraisalQuery) use ($actorEmployee): void {
                $this->scopeAppraisalQuery($appraisalQuery, $actorEmployee);
            });

            return;
        }

        if ($modelClass === AppraisalReview::class) {
            if ($user?->can('hrms.appraisal.manage') ?? false) {
                return;
            }

            $actorEmployee = $this->findActorEmployee($request);
            if ($actorEmployee === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $actorId = $actorEmployee->id;
            $query->where(function (Builder $sub) use ($actorId): void {
                $sub->where('reviewer_id', $actorId)
                    ->orWhereHas('appraisal', function (Builder $appraisalQuery) use ($actorId): void {
                        $appraisalQuery->where(function (Builder $inner) use ($actorId): void {
                            $inner->where(function (Builder $empCheck) use ($actorId): void {
                                $empCheck->where('employee_id', $actorId)
                                    ->where('status', AppraisalStatus::Complete->value);
                            })
                                ->orWhere('supervisor_id', $actorId)
                                ->orWhere('hod_id', $actorId)
                                ->orWhere('hr_reviewer_id', $actorId);
                        });
                    });
            });

            return;
        }

        if ($modelClass === StaffPromotion::class) {
            if ($user?->can('hrms.promotion.approve') ?? false) {
                return;
            }

            $actorEmployee = $this->findActorEmployee($request);
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
    }

    private function authorizeRecordVisibility(Request $request, Model $record): void
    {
        $user = $request->user();

        if (
            $record instanceof JobRequisition
            || $record instanceof JobPosting
            || $record instanceof Candidate
            || $record instanceof CandidateApplication
            || $record instanceof CandidateDocument
            || $record instanceof CandidateReference
            || $record instanceof CandidateAssessment
            || $record instanceof InterviewStage
            || $record instanceof Interview
            || $record instanceof InterviewPanel
            || $record instanceof InterviewEvaluation
            || $record instanceof JobOffer
            || $record instanceof OfferNegotiation
            || $record instanceof OnboardingTask
        ) {
            abort_if(! $this->canViewRecruitment($user), 403);

            return;
        }

        if ($record instanceof LeaveRequest) {
            if ($this->canManageLeave($user)) {
                return;
            }

            $actorEmployee = $this->findActorEmployee($request);
            $actorId = $actorEmployee?->id;

            $isParty = $actorId !== null && in_array($actorId, [
                $record->employee_id,
                $record->supervisor_id,
                $record->hod_id,
                $record->hr_verifier_id,
                $record->relieving_officer_id,
            ], true);

            abort_if(! $isParty, 403);

            return;
        }

        if ($record instanceof LeaveBalance) {
            if ($this->canManageLeave($user)) {
                return;
            }

            $actorEmployee = $this->findActorEmployee($request);
            abort_if($actorEmployee === null || $record->employee_id !== $actorEmployee->id, 403);

            return;
        }

        if ($record instanceof Appraisal) {
            if ($user?->can('hrms.appraisal.manage') ?? false) {
                return;
            }

            $actorEmployee = $this->findActorEmployee($request);
            abort_if(! $this->isPartyToAppraisal($record, $actorEmployee), 403);

            return;
        }

        if (
            $record instanceof AppraisalResponse
            || $record instanceof AppraisalGoal
            || $record instanceof AppraisalScore
            || $record instanceof AppraisalRecommendation
        ) {
            if ($user?->can('hrms.appraisal.manage') ?? false) {
                return;
            }

            $actorEmployee = $this->findActorEmployee($request);
            $appraisal = $record->appraisal;
            abort_if(! $appraisal instanceof Appraisal || ! $this->isPartyToAppraisal($appraisal, $actorEmployee), 403);

            return;
        }

        if ($record instanceof AppraisalReview) {
            if ($user?->can('hrms.appraisal.manage') ?? false) {
                return;
            }

            $actorEmployee = $this->findActorEmployee($request);
            $appraisal = $record->appraisal;
            abort_if(! $appraisal instanceof Appraisal || ! $this->isPartyToAppraisal($appraisal, $actorEmployee), 403);

            $isReviewerOnChain = $actorEmployee !== null
                && (
                    in_array($actorEmployee->id, [
                        $appraisal->supervisor_id,
                        $appraisal->hod_id,
                        $appraisal->hr_reviewer_id,
                        $record->reviewer_id,
                    ], true)
                    || $appraisal->reviews()->where('reviewer_id', $actorEmployee->id)->exists()
                );

            abort_if(
                ! $isReviewerOnChain && $appraisal->status !== AppraisalStatus::Complete,
                403
            );

            return;
        }

        if ($record instanceof StaffPromotion) {
            if ($user?->can('hrms.promotion.approve') ?? false) {
                return;
            }

            $actorEmployee = $this->findActorEmployee($request);
            abort_if(
                $actorEmployee === null
                || ! in_array($actorEmployee->id, [
                    $record->employee_id,
                    $record->supervisor_id,
                    $record->hr_approver_id,
                ], true),
                403
            );

            return;
        }

        if ($record instanceof SalaryLevel || $record instanceof SalaryStep) {
            abort_if(! $this->canViewSalary($user), 403);
        }
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function scopeAppraisalQuery(Builder $query, ?Employee $actorEmployee): void
    {
        if ($actorEmployee === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $actorId = $actorEmployee->id;
        $query->where(function (Builder $sub) use ($actorId): void {
            $sub->where('employee_id', $actorId)
                ->orWhere('supervisor_id', $actorId)
                ->orWhere('hod_id', $actorId)
                ->orWhere('hr_reviewer_id', $actorId)
                ->orWhereHas('reviews', function (Builder $revQuery) use ($actorId): void {
                    $revQuery->where('reviewer_id', $actorId);
                });
        });
    }

    private function isPartyToAppraisal(Appraisal $appraisal, ?Employee $actorEmployee): bool
    {
        if ($actorEmployee === null) {
            return false;
        }

        if (in_array($actorEmployee->id, [
            $appraisal->employee_id,
            $appraisal->supervisor_id,
            $appraisal->hod_id,
            $appraisal->hr_reviewer_id,
        ], true)) {
            return true;
        }

        return $appraisal->reviews()->where('reviewer_id', $actorEmployee->id)->exists();
    }

    private function findActorEmployee(Request $request): ?Employee
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        return Employee::query()
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id);
                if ($user->email !== '') {
                    $query->orWhere('email', $user->email);
                }
            })
            ->first();
    }

    /**
     * @return array{0: class-string, 1: class-string<JsonResource>, 2: array<int, string>, 3: array<int, string>}
     */
    private function resolveRouteConfig(Request $request): array
    {
        $route = $request->route();

        $defaults = is_object($route) ? $route->defaults : [];

        $modelClass = Arr::get($defaults, 'model');
        $resourceClass = Arr::get($defaults, 'resource');

        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            abort(500, 'Invalid or missing model configuration for API route.');
        }

        if (! is_string($resourceClass) || ! class_exists($resourceClass) || ! is_subclass_of($resourceClass, JsonResource::class)) {
            abort(500, 'Invalid or missing resource configuration for API route.');
        }

        $with = Arr::wrap(Arr::get($defaults, 'with', []));
        $withCount = Arr::wrap(Arr::get($defaults, 'withCount', []));

        return [$modelClass, $resourceClass, $with, $withCount];
    }

    private function canViewSalary(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->can('hrms.salary.view') || $user->can('hrms.salary.manage');
    }

    private function canViewRecruitment(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->can('hrms.jobs.view')
            || $user->can('hrms.jobs.manage')
            || $user->can('hrms.applications.view')
            || $user->can('hrms.applications.manage');
    }

    private function canManageLeave(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->can('hrms.leave.view') || $user->can('hrms.leave.approve');
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = $request->integer('per_page', self::DEFAULT_PER_PAGE);

        if ($perPage < 1) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        return min($perPage, self::MAX_PER_PAGE);
    }
}
