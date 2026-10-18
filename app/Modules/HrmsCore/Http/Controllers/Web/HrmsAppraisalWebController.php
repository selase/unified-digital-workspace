<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\HrmsCore\Enums\AppraisalCycle;
use App\Modules\HrmsCore\Enums\AppraisalStatus;
use App\Modules\HrmsCore\Enums\GoalStatus;
use App\Modules\HrmsCore\Enums\RecommendationType;
use App\Modules\HrmsCore\Http\Requests\Appraisal\AppraisalPeriodRequest;
use App\Modules\HrmsCore\Http\Requests\Appraisal\EvaluateAppraisalCompetencyRequest;
use App\Modules\HrmsCore\Http\Requests\Appraisal\EvaluateAppraisalGoalRequest;
use App\Modules\HrmsCore\Http\Requests\Appraisal\StoreAppraisalCommentRequest;
use App\Modules\HrmsCore\Http\Requests\Appraisal\StoreAppraisalCompetencyRequest;
use App\Modules\HrmsCore\Http\Requests\Appraisal\StoreAppraisalGoalRequest;
use App\Modules\HrmsCore\Http\Requests\Appraisal\StoreAppraisalRatingScaleRequest;
use App\Modules\HrmsCore\Http\Requests\Appraisal\StoreAppraisalRecommendationRequest;
use App\Modules\HrmsCore\Http\Requests\Appraisal\StoreAppraisalRequest;
use App\Modules\HrmsCore\Http\Requests\Appraisal\StoreAppraisalReviewRequest;
use App\Modules\HrmsCore\Http\Requests\Appraisal\StoreAppraisalTemplateRequest;
use App\Modules\HrmsCore\Http\Requests\Appraisal\TransitionAppraisalStatusRequest;
use App\Modules\HrmsCore\Models\Appraisal\Appraisal;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalComment;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalCompetency;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalGoal;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalPeriod;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalRatingScale;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalRecommendation;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalReview;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalTemplate;
use App\Modules\HrmsCore\Models\Employees\Employee;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class HrmsAppraisalWebController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->activeTenantId($request);
        $user = $request->user();
        $actorEmployee = $this->findActorEmployee($request, $tenantId);
        $isHrManage = $user?->can('hrms.appraisal.manage') ?? false;
        $canViewPermission = $user?->can('hrms.appraisal.view') ?? false;

        abort_if(! $isHrManage && ! $canViewPermission && $actorEmployee === null, 403);

        $search = mb_trim((string) $request->string('search'));
        $statusFilter = (string) $request->string('status');
        $periodFilter = $request->integer('period_id');
        $scope = (string) $request->string('scope');

        $baseQuery = Appraisal::query()->where('tenant_id', $tenantId);
        $this->applyVisibilityScope($baseQuery, $isHrManage, $actorEmployee, $scope);

        $appraisals = (clone $baseQuery)
            ->with([
                'employee:id,uuid,first_name,last_name,employee_staff_id',
                'period:id,uuid,name,cycle,start_date,end_date',
                'template:id,uuid,name',
                'supervisor:id,first_name,last_name',
            ])
            ->withCount(['competencies', 'goals', 'comments', 'reviews'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('employee', function ($employeeQuery) use ($search): void {
                    $employeeQuery->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_staff_id', 'like', "%{$search}%");
                });
            })
            ->when($statusFilter !== '', function ($query) use ($statusFilter): void {
                $query->where('status', $statusFilter);
            })
            ->when($periodFilter > 0, function ($query) use ($periodFilter): void {
                $query->where('period_id', $periodFilter);
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $myAppraisals = $actorEmployee !== null
            ? Appraisal::query()
                ->where('tenant_id', $tenantId)
                ->where('employee_id', $actorEmployee->id)
                ->with(['period:id,name,cycle', 'template:id,name', 'supervisor:id,first_name,last_name'])
                ->latest('id')
                ->get()
            : collect();

        $periods = AppraisalPeriod::query()
            ->where('tenant_id', $tenantId)
            ->withCount(['appraisals', 'templates', 'ratingScales'])
            ->orderByDesc('start_date')
            ->get();

        $templates = AppraisalTemplate::query()
            ->where('tenant_id', $tenantId)
            ->with('period:id,name')
            ->withCount('appraisals')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $ratingScales = AppraisalRatingScale::query()
            ->where('tenant_id', $tenantId)
            ->with('period:id,name')
            ->orderBy('period_id')
            ->orderBy('value')
            ->get();

        $employees = Employee::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'employee_staff_id']);

        $totalAppraisals = (clone $baseQuery)->count();
        $inSelfAssessment = (clone $baseQuery)
            ->where('status', AppraisalStatus::SelfAssessment->value)
            ->count();
        $inReview = (clone $baseQuery)
            ->whereIn('status', [
                AppraisalStatus::SupervisorReview->value,
                AppraisalStatus::HodReview->value,
                AppraisalStatus::HrReview->value,
            ])
            ->count();
        $completedAppraisals = (clone $baseQuery)
            ->where('status', AppraisalStatus::Complete->value)
            ->count();
        $activePeriodsCount = AppraisalPeriod::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();

        return view('hrms-core::appraisals', [
            'appraisals' => $appraisals,
            'myAppraisals' => $myAppraisals,
            'actorEmployee' => $actorEmployee,
            'isHrManage' => $isHrManage,
            'scope' => $scope,
            'periods' => $periods,
            'templates' => $templates,
            'ratingScales' => $ratingScales,
            'employees' => $employees,
            'cycles' => AppraisalCycle::cases(),
            'statuses' => AppraisalStatus::cases(),
            'search' => $search,
            'statusFilter' => $statusFilter,
            'periodFilter' => $periodFilter,
            'totalAppraisals' => $totalAppraisals,
            'inSelfAssessment' => $inSelfAssessment,
            'inReview' => $inReview,
            'completedAppraisals' => $completedAppraisals,
            'activePeriodsCount' => $activePeriodsCount,
        ]);
    }

    public function show(Request $request, Appraisal $appraisal): View
    {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($appraisal, $tenantId);

        $user = $request->user();
        $actorEmployee = $this->findActorEmployee($request, $tenantId);
        $isHrManage = $user?->can('hrms.appraisal.manage') ?? false;

        abort_if(! $isHrManage && ! $this->isPartyToAppraisal($appraisal, $actorEmployee), 403);

        $canViewSupervisorAssessment = $this->canViewSupervisorAssessment($appraisal, $actorEmployee, $isHrManage);
        $canViewPrivateComments = $isHrManage || $this->isReviewerOnAppraisal($appraisal, $actorEmployee);

        $appraisal->load([
            'employee.grade',
            'employee.departments',
            'period',
            'template',
            'supervisor',
            'hod',
            'hrReviewer',
            'competencies' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'goals' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'reviews.reviewer',
            'comments' => fn ($query) => $query
                ->when(! $canViewPrivateComments, fn ($q) => $q->where('is_private', false))
                ->with('author')
                ->orderByDesc('created_at'),
            'recommendations.recommendedBy',
            'recommendations.approvedBy',
        ]);

        $ratingScales = AppraisalRatingScale::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($appraisal): void {
                $query->where('period_id', $appraisal->period_id)
                    ->orWhereNull('period_id');
            })
            ->orderByDesc('period_id')
            ->orderBy('value')
            ->get()
            ->unique('value')
            ->values();

        $employees = Employee::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'employee_staff_id']);

        return view('hrms-core::appraisals-show', [
            'appraisal' => $appraisal,
            'actorEmployee' => $actorEmployee,
            'isHrManage' => $isHrManage,
            'canViewSupervisorAssessment' => $canViewSupervisorAssessment,
            'canViewPrivateComments' => $canViewPrivateComments,
            'ratingScales' => $ratingScales,
            'employees' => $employees,
            'statuses' => AppraisalStatus::cases(),
            'goalStatuses' => GoalStatus::cases(),
            'recommendationTypes' => RecommendationType::cases(),
            'reviewerTypes' => AppraisalReview::reviewerTypes(),
            'reviewDecisions' => AppraisalReview::decisions(),
            'commentTypes' => AppraisalComment::types(),
        ]);
    }

    public function storePeriod(AppraisalPeriodRequest $request): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $tenantId, $request): void {
            $name = (string) $validated['name'];

            $period = AppraisalPeriod::query()->create([
                'tenant_id' => $tenantId,
                'name' => $name,
                'slug' => $this->uniquePeriodSlug($name),
                'cycle' => (string) $validated['cycle'],
                'start_date' => (string) $validated['start_date'],
                'end_date' => (string) $validated['end_date'],
                'description' => isset($validated['description']) ? (string) $validated['description'] : null,
                'is_active' => $request->boolean('is_active', true),
            ]);

            if ($request->boolean('seed_default_scales', true)) {
                $this->seedDefaultScalesForPeriod($tenantId, $period->id);
            }

            if ($request->boolean('create_default_template', true)) {
                $templateName = "{$period->name} Standard Template";
                AppraisalTemplate::query()->create([
                    'tenant_id' => $tenantId,
                    'period_id' => $period->id,
                    'name' => $templateName,
                    'slug' => $this->uniqueTemplateSlug($templateName),
                    'description' => "Standard evaluation template for {$period->name}.",
                    'is_default' => true,
                    'is_active' => true,
                    'sort_order' => 0,
                ]);
            }
        });

        return back()->with('status', 'Appraisal period created.');
    }

    public function updatePeriod(AppraisalPeriodRequest $request, AppraisalPeriod $period): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($period, $tenantId);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $period->update([
            'name' => (string) $validated['name'],
            'cycle' => (string) $validated['cycle'],
            'start_date' => (string) $validated['start_date'],
            'end_date' => (string) $validated['end_date'],
            'description' => isset($validated['description']) ? (string) $validated['description'] : null,
            'is_active' => $request->boolean('is_active', $period->is_active),
        ]);

        return back()->with('status', 'Appraisal period updated.');
    }

    public function storeTemplate(StoreAppraisalTemplateRequest $request): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $name = (string) $validated['name'];

        AppraisalTemplate::query()->create([
            'tenant_id' => $tenantId,
            'period_id' => isset($validated['period_id']) ? (int) $validated['period_id'] : null,
            'name' => $name,
            'slug' => $this->uniqueTemplateSlug($name),
            'description' => isset($validated['description']) ? (string) $validated['description'] : null,
            'is_default' => $request->boolean('is_default'),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => isset($validated['sort_order']) ? (int) $validated['sort_order'] : 0,
        ]);

        return back()->with('status', 'Appraisal template created.');
    }

    public function storeRatingScale(StoreAppraisalRatingScaleRequest $request): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $periodId = isset($validated['period_id']) ? (int) $validated['period_id'] : null;
        $value = (int) $validated['value'];

        AppraisalRatingScale::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'period_id' => $periodId,
                'value' => $value,
            ],
            [
                'label' => (string) $validated['label'],
                'description' => isset($validated['description']) ? (string) $validated['description'] : null,
                'color' => isset($validated['color']) ? (string) $validated['color'] : null,
            ]
        );

        return back()->with('status', 'Appraisal rating scale saved.');
    }

    public function destroyRatingScale(Request $request, AppraisalRatingScale $ratingScale): RedirectResponse
    {
        abort_if(! ($request->user()?->can('hrms.appraisal.manage') ?? false), 403);

        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($ratingScale, $tenantId);

        $ratingScale->delete();

        return back()->with('status', 'Appraisal rating scale deleted.');
    }

    public function storeAppraisal(StoreAppraisalRequest $request): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $employeeId = (int) $validated['employee_id'];
        $periodId = (int) $validated['period_id'];

        $existing = Appraisal::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->where('period_id', $periodId)
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'employee_id' => 'An appraisal already exists for this employee in the selected period.',
            ]);
        }

        $appraisal = Appraisal::query()->create([
            'tenant_id' => $tenantId,
            'employee_id' => $employeeId,
            'period_id' => $periodId,
            'template_id' => (int) $validated['template_id'],
            'status' => AppraisalStatus::Draft->value,
            'supervisor_id' => isset($validated['supervisor_id']) ? (int) $validated['supervisor_id'] : null,
            'hod_id' => isset($validated['hod_id']) ? (int) $validated['hod_id'] : null,
            'hr_reviewer_id' => isset($validated['hr_reviewer_id']) ? (int) $validated['hr_reviewer_id'] : null,
        ]);

        return redirect()
            ->route('hrms-core.appraisals.show', $appraisal)
            ->with('status', 'Employee appraisal initiated.');
    }

    public function transitionStatus(TransitionAppraisalStatusRequest $request, Appraisal $appraisal): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($appraisal, $tenantId);

        $user = $request->user();
        $actorEmployee = $this->findActorEmployee($request, $tenantId);
        $isHrManage = $user?->can('hrms.appraisal.manage') ?? false;

        abort_if(! $isHrManage && ! $this->isPartyToAppraisal($appraisal, $actorEmployee), 403);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $targetStatus = AppraisalStatus::from((string) $validated['status']);

        if (! $appraisal->status->canTransitionTo($targetStatus)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition appraisal from {$appraisal->status->label()} to {$targetStatus->label()}.",
            ]);
        }

        $isAuthorizedStageActor = $this->isAuthorizedForStageTransition($request, $appraisal, $targetStatus, $actorEmployee);
        if (! $isAuthorizedStageActor) {
            abort_if(! $isHrManage, 403);
            $this->logHrOverride($request, $appraisal, 'appraisal.status.override', [
                'from_status' => $appraisal->status->value,
                'to_status' => $targetStatus->value,
                'actor_employee_id' => $actorEmployee?->id,
            ]);
        }

        if ($targetStatus === AppraisalStatus::SupervisorReview) {
            $hasItems = $appraisal->competencies()->exists() || $appraisal->goals()->exists();
            $hasRatedSelf = $appraisal->competencies()->whereNotNull('self_rating')->exists()
                || $appraisal->goals()->whereNotNull('self_rating')->exists()
                || $appraisal->responses()->whereNotNull('self_rating')->exists();

            if ($hasItems && ! $hasRatedSelf) {
                throw ValidationException::withMessages([
                    'status' => 'At least one competency or goal must be self-rated before submitting for supervisor review.',
                ]);
            }
        }

        $appraisal->transitionTo($targetStatus);
        $this->recalculateAndPersistScores($appraisal);

        return back()->with('status', "Appraisal workflow moved to {$targetStatus->label()}.");
    }

    public function destroyAppraisal(Request $request, Appraisal $appraisal): RedirectResponse
    {
        $user = $request->user();
        abort_if(
            ! ($user?->can('hrms.appraisal.create') ?? false)
            && ! ($user?->can('hrms.appraisal.manage') ?? false),
            403
        );

        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($appraisal, $tenantId);

        if (! in_array($appraisal->status, [AppraisalStatus::Draft, AppraisalStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'appraisal' => 'Only draft or cancelled appraisals can be deleted.',
            ]);
        }

        $appraisal->delete();

        return redirect()
            ->route('hrms-core.appraisals.index')
            ->with('status', 'Appraisal deleted.');
    }

    public function storeCompetency(StoreAppraisalCompetencyRequest $request, Appraisal $appraisal): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($appraisal, $tenantId);
        $this->assertAppraisalMutable($appraisal);

        $user = $request->user();
        $actorEmployee = $this->findActorEmployee($request, $tenantId);
        $isHrManage = $user?->can('hrms.appraisal.manage') ?? false;
        abort_if(
            ! $isHrManage
            && ! ($user?->can('hrms.appraisal.create') ?? false)
            && ! $this->isPartyToAppraisal($appraisal, $actorEmployee),
            403
        );

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        AppraisalCompetency::query()->create([
            'tenant_id' => $tenantId,
            'appraisal_id' => $appraisal->id,
            'name' => (string) $validated['name'],
            'description' => isset($validated['description']) ? (string) $validated['description'] : null,
            'behavioral_indicators' => isset($validated['behavioral_indicators']) ? (string) $validated['behavioral_indicators'] : null,
            'weight' => isset($validated['weight']) ? (float) $validated['weight'] : 0,
            'sort_order' => isset($validated['sort_order']) ? (int) $validated['sort_order'] : 0,
        ]);

        return back()->with('status', 'Appraisal competency added.');
    }

    public function updateCompetency(
        EvaluateAppraisalCompetencyRequest $request,
        Appraisal $appraisal,
        AppraisalCompetency $competency
    ): RedirectResponse {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($appraisal, $tenantId);
        $this->assertTenantModel($competency, $tenantId);
        abort_if($competency->appraisal_id !== $appraisal->id, 404);
        $this->assertAppraisalMutable($appraisal);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $this->authorizeEvaluationFields(
            $request,
            $appraisal,
            'competency',
            $competency->id,
            $this->hasSelfFields($validated, ['self_rating', 'self_evidence']),
            $this->hasSupervisorFields($validated, ['supervisor_rating', 'supervisor_evidence']),
            array_key_exists('final_rating', $validated) && $validated['final_rating'] !== null
        );

        $competency->update([
            'name' => isset($validated['name']) && $validated['name'] !== '' ? (string) $validated['name'] : $competency->name,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $competency->description,
            'behavioral_indicators' => array_key_exists('behavioral_indicators', $validated) ? $validated['behavioral_indicators'] : $competency->behavioral_indicators,
            'self_rating' => array_key_exists('self_rating', $validated) && $validated['self_rating'] !== null
                ? (int) $validated['self_rating']
                : $competency->self_rating,
            'self_evidence' => array_key_exists('self_evidence', $validated) ? $validated['self_evidence'] : $competency->self_evidence,
            'supervisor_rating' => array_key_exists('supervisor_rating', $validated) && $validated['supervisor_rating'] !== null
                ? (int) $validated['supervisor_rating']
                : $competency->supervisor_rating,
            'supervisor_evidence' => array_key_exists('supervisor_evidence', $validated) ? $validated['supervisor_evidence'] : $competency->supervisor_evidence,
            'final_rating' => array_key_exists('final_rating', $validated) && $validated['final_rating'] !== null
                ? (int) $validated['final_rating']
                : $competency->final_rating,
            'weight' => isset($validated['weight']) ? (float) $validated['weight'] : (float) $competency->weight,
            'sort_order' => isset($validated['sort_order']) ? (int) $validated['sort_order'] : $competency->sort_order,
        ]);

        $this->recalculateAndPersistScores($appraisal);

        return back()->with('status', 'Competency evaluation saved.');
    }

    public function destroyCompetency(
        Request $request,
        Appraisal $appraisal,
        AppraisalCompetency $competency
    ): RedirectResponse {
        $user = $request->user();
        abort_if(
            ! ($user?->can('hrms.appraisal.create') ?? false)
            && ! ($user?->can('hrms.appraisal.manage') ?? false),
            403
        );

        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($appraisal, $tenantId);
        $this->assertTenantModel($competency, $tenantId);
        abort_if($competency->appraisal_id !== $appraisal->id, 404);
        $this->assertAppraisalMutable($appraisal);

        $competency->delete();
        $this->recalculateAndPersistScores($appraisal);

        return back()->with('status', 'Competency removed.');
    }

    public function storeGoal(StoreAppraisalGoalRequest $request, Appraisal $appraisal): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($appraisal, $tenantId);
        $this->assertAppraisalMutable($appraisal);

        $user = $request->user();
        $actorEmployee = $this->findActorEmployee($request, $tenantId);
        $isHrManage = $user?->can('hrms.appraisal.manage') ?? false;
        abort_if(
            ! $isHrManage
            && ! ($user?->can('hrms.appraisal.create') ?? false)
            && ! $this->isPartyToAppraisal($appraisal, $actorEmployee),
            403
        );

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        AppraisalGoal::query()->create([
            'tenant_id' => $tenantId,
            'appraisal_id' => $appraisal->id,
            'title' => (string) $validated['title'],
            'description' => isset($validated['description']) ? (string) $validated['description'] : null,
            'key_results' => isset($validated['key_results']) ? (string) $validated['key_results'] : null,
            'target' => isset($validated['target']) ? (string) $validated['target'] : null,
            'status' => GoalStatus::NotStarted->value,
            'weight' => isset($validated['weight']) ? (float) $validated['weight'] : 0,
            'due_date' => isset($validated['due_date']) ? (string) $validated['due_date'] : null,
            'sort_order' => isset($validated['sort_order']) ? (int) $validated['sort_order'] : 0,
        ]);

        return back()->with('status', 'Appraisal goal added.');
    }

    public function updateGoal(
        EvaluateAppraisalGoalRequest $request,
        Appraisal $appraisal,
        AppraisalGoal $goal
    ): RedirectResponse {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($appraisal, $tenantId);
        $this->assertTenantModel($goal, $tenantId);
        abort_if($goal->appraisal_id !== $appraisal->id, 404);
        $this->assertAppraisalMutable($appraisal);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $this->authorizeEvaluationFields(
            $request,
            $appraisal,
            'goal',
            $goal->id,
            $this->hasSelfFields($validated, ['self_rating', 'employee_comments', 'achievement']),
            $this->hasSupervisorFields($validated, ['supervisor_rating', 'supervisor_comments']),
            false
        );

        $goal->update([
            'title' => isset($validated['title']) && $validated['title'] !== '' ? (string) $validated['title'] : $goal->title,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $goal->description,
            'key_results' => array_key_exists('key_results', $validated) ? $validated['key_results'] : $goal->key_results,
            'target' => array_key_exists('target', $validated) ? $validated['target'] : $goal->target,
            'achievement' => array_key_exists('achievement', $validated) ? $validated['achievement'] : $goal->achievement,
            'status' => isset($validated['status']) ? (string) $validated['status'] : $goal->status->value,
            'weight' => isset($validated['weight']) ? (float) $validated['weight'] : (float) $goal->weight,
            'self_rating' => array_key_exists('self_rating', $validated) && $validated['self_rating'] !== null
                ? (int) $validated['self_rating']
                : $goal->self_rating,
            'supervisor_rating' => array_key_exists('supervisor_rating', $validated) && $validated['supervisor_rating'] !== null
                ? (int) $validated['supervisor_rating']
                : $goal->supervisor_rating,
            'employee_comments' => array_key_exists('employee_comments', $validated) ? $validated['employee_comments'] : $goal->employee_comments,
            'supervisor_comments' => array_key_exists('supervisor_comments', $validated) ? $validated['supervisor_comments'] : $goal->supervisor_comments,
            'due_date' => array_key_exists('due_date', $validated) ? $validated['due_date'] : $goal->due_date?->toDateString(),
        ]);

        $this->recalculateAndPersistScores($appraisal);

        return back()->with('status', 'Goal evaluation saved.');
    }

    public function destroyGoal(
        Request $request,
        Appraisal $appraisal,
        AppraisalGoal $goal
    ): RedirectResponse {
        $user = $request->user();
        abort_if(
            ! ($user?->can('hrms.appraisal.create') ?? false)
            && ! ($user?->can('hrms.appraisal.manage') ?? false),
            403
        );

        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($appraisal, $tenantId);
        $this->assertTenantModel($goal, $tenantId);
        abort_if($goal->appraisal_id !== $appraisal->id, 404);
        $this->assertAppraisalMutable($appraisal);

        $goal->delete();
        $this->recalculateAndPersistScores($appraisal);

        return back()->with('status', 'Goal removed.');
    }

    public function storeReview(StoreAppraisalReviewRequest $request, Appraisal $appraisal): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($appraisal, $tenantId);
        $this->assertAppraisalMutable($appraisal);

        $user = $request->user();
        $actorEmployee = $this->findActorEmployee($request, $tenantId);
        $isHrManage = $user?->can('hrms.appraisal.manage') ?? false;
        abort_if(! $isHrManage && ! $this->isReviewerOnAppraisal($appraisal, $actorEmployee), 403);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $reviewerId = ($isHrManage && isset($validated['reviewer_id']))
            ? (int) $validated['reviewer_id']
            : $this->requireActorEmployeeId($request, $tenantId);

        $reviewerType = (string) $validated['reviewer_type'];

        AppraisalReview::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'appraisal_id' => $appraisal->id,
                'reviewer_type' => $reviewerType,
            ],
            [
                'reviewer_id' => $reviewerId,
                'overall_rating' => isset($validated['overall_rating']) ? (float) $validated['overall_rating'] : null,
                'strengths' => isset($validated['strengths']) ? (string) $validated['strengths'] : null,
                'areas_for_improvement' => isset($validated['areas_for_improvement']) ? (string) $validated['areas_for_improvement'] : null,
                'general_comments' => isset($validated['general_comments']) ? (string) $validated['general_comments'] : null,
                'decision' => (string) $validated['decision'],
                'reviewed_at' => Carbon::now(),
            ]
        );

        return back()->with('status', 'Stage review recorded.');
    }

    public function storeComment(StoreAppraisalCommentRequest $request, Appraisal $appraisal): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($appraisal, $tenantId);

        $user = $request->user();
        $actorEmployee = $this->findActorEmployee($request, $tenantId);
        $isHrManage = $user?->can('hrms.appraisal.manage') ?? false;
        abort_if(! $isHrManage && ! $this->isPartyToAppraisal($appraisal, $actorEmployee), 403);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $authorId = ($isHrManage && isset($validated['author_id']))
            ? (int) $validated['author_id']
            : $this->requireActorEmployeeId($request, $tenantId);

        AppraisalComment::query()->create([
            'tenant_id' => $tenantId,
            'appraisal_id' => $appraisal->id,
            'author_id' => $authorId,
            'comment' => (string) $validated['comment'],
            'type' => (string) $validated['type'],
            'is_private' => $request->boolean('is_private'),
        ]);

        return back()->with('status', 'Appraisal comment added.');
    }

    public function destroyComment(
        Request $request,
        Appraisal $appraisal,
        AppraisalComment $comment
    ): RedirectResponse {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($appraisal, $tenantId);
        $this->assertTenantModel($comment, $tenantId);
        abort_if($comment->appraisal_id !== $appraisal->id, 404);

        $user = $request->user();
        $actorEmployee = $this->findActorEmployee($request, $tenantId);
        $isHrManage = $user?->can('hrms.appraisal.manage') ?? false;
        abort_if(! $isHrManage && $actorEmployee?->id !== $comment->author_id, 403);

        $comment->delete();

        return back()->with('status', 'Appraisal comment deleted.');
    }

    public function storeRecommendation(
        StoreAppraisalRecommendationRequest $request,
        Appraisal $appraisal
    ): RedirectResponse {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($appraisal, $tenantId);

        $user = $request->user();
        $actorEmployee = $this->findActorEmployee($request, $tenantId);
        $isHrManage = $user?->can('hrms.appraisal.manage') ?? false;
        abort_if(! $isHrManage && ! $this->isReviewerOnAppraisal($appraisal, $actorEmployee), 403);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $recommendedBy = ($isHrManage && isset($validated['recommended_by']))
            ? (int) $validated['recommended_by']
            : $this->requireActorEmployeeId($request, $tenantId);

        $status = isset($validated['status'])
            ? (string) $validated['status']
            : AppraisalRecommendation::STATUS_PENDING;

        AppraisalRecommendation::query()->create([
            'tenant_id' => $tenantId,
            'appraisal_id' => $appraisal->id,
            'type' => (string) $validated['type'],
            'description' => isset($validated['description']) ? (string) $validated['description'] : null,
            'action_plan' => isset($validated['action_plan']) ? (string) $validated['action_plan'] : null,
            'target_date' => isset($validated['target_date']) ? (string) $validated['target_date'] : null,
            'recommended_by' => $recommendedBy,
            'approved_by' => $status === AppraisalRecommendation::STATUS_APPROVED ? $recommendedBy : null,
            'approved_at' => $status === AppraisalRecommendation::STATUS_APPROVED ? Carbon::now() : null,
            'status' => $status,
        ]);

        return back()->with('status', 'Appraisal recommendation recorded.');
    }

    /**
     * @param  Builder<Appraisal>  $query
     */
    private function applyVisibilityScope(
        Builder $query,
        bool $isHrManage,
        ?Employee $actorEmployee,
        string $scope
    ): void {
        if ($scope === 'mine') {
            $query->where('employee_id', $actorEmployee !== null ? $actorEmployee->id : -1);

            return;
        }

        if ($isHrManage) {
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
                ->orWhere('hod_id', $actorId)
                ->orWhere('hr_reviewer_id', $actorId);
        });
    }

    private function isPartyToAppraisal(Appraisal $appraisal, ?Employee $actorEmployee): bool
    {
        if ($actorEmployee === null) {
            return false;
        }

        return in_array($actorEmployee->id, [
            $appraisal->employee_id,
            $appraisal->supervisor_id,
            $appraisal->hod_id,
            $appraisal->hr_reviewer_id,
        ], true);
    }

    private function isReviewerOnAppraisal(Appraisal $appraisal, ?Employee $actorEmployee): bool
    {
        if ($actorEmployee === null) {
            return false;
        }

        return in_array($actorEmployee->id, [
            $appraisal->supervisor_id,
            $appraisal->hod_id,
            $appraisal->hr_reviewer_id,
        ], true);
    }

    private function canViewSupervisorAssessment(
        Appraisal $appraisal,
        ?Employee $actorEmployee,
        bool $isHrManage
    ): bool {
        if ($isHrManage || $appraisal->status === AppraisalStatus::Complete) {
            return true;
        }

        return $this->isReviewerOnAppraisal($appraisal, $actorEmployee);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<int, string>  $keys
     */
    private function hasSelfFields(array $validated, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $validated) && $validated[$key] !== null && $validated[$key] !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<int, string>  $keys
     */
    private function hasSupervisorFields(array $validated, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $validated) && $validated[$key] !== null && $validated[$key] !== '') {
                return true;
            }
        }

        return false;
    }

    private function authorizeEvaluationFields(
        Request $request,
        Appraisal $appraisal,
        string $itemType,
        int $itemId,
        bool $writingSelf,
        bool $writingSupervisor,
        bool $writingFinal
    ): void {
        $tenantId = $this->activeTenantId($request);
        $user = $request->user();
        $actorEmployee = $this->findActorEmployee($request, $tenantId);
        $isHrManage = $user?->can('hrms.appraisal.manage') ?? false;
        $hasEvaluatePerm = $user?->can('hrms.appraisal.evaluate') ?? false;

        abort_if(! $isHrManage && ! $this->isPartyToAppraisal($appraisal, $actorEmployee), 403);

        if ($actorEmployee?->id !== $appraisal->employee_id && ! $hasEvaluatePerm && ! $isHrManage) {
            abort(403);
        }

        if ($writingSelf) {
            $isEmployeeInSelfStage = $actorEmployee !== null
                && $actorEmployee->id === $appraisal->employee_id
                && $appraisal->status === AppraisalStatus::SelfAssessment;

            if (! $isEmployeeInSelfStage) {
                abort_if(! $isHrManage, 403);
                $this->logHrOverride($request, $appraisal, "appraisal.{$itemType}.self_rating.override", [
                    'item_type' => $itemType,
                    'item_id' => $itemId,
                    'appraisal_status' => $appraisal->status->value,
                    'actor_employee_id' => $actorEmployee?->id,
                ]);
            }
        }

        if ($writingSupervisor) {
            $isSupervisorInReviewStage = $actorEmployee !== null
                && ($actorEmployee->id === $appraisal->supervisor_id || $appraisal->canReview($actorEmployee))
                && $appraisal->status === AppraisalStatus::SupervisorReview
                && $hasEvaluatePerm;

            if (! $isSupervisorInReviewStage) {
                abort_if(! $isHrManage, 403);
                $this->logHrOverride($request, $appraisal, "appraisal.{$itemType}.supervisor_rating.override", [
                    'item_type' => $itemType,
                    'item_id' => $itemId,
                    'appraisal_status' => $appraisal->status->value,
                    'actor_employee_id' => $actorEmployee?->id,
                ]);
            }
        }

        if ($writingFinal) {
            $isFinalReviewerInStage = $actorEmployee !== null
                && in_array($actorEmployee->id, [$appraisal->hod_id, $appraisal->hr_reviewer_id], true)
                && in_array($appraisal->status, [AppraisalStatus::HodReview, AppraisalStatus::HrReview], true)
                && $hasEvaluatePerm;

            if (! $isFinalReviewerInStage) {
                abort_if(! $isHrManage, 403);
                $this->logHrOverride($request, $appraisal, "appraisal.{$itemType}.final_rating.override", [
                    'item_type' => $itemType,
                    'item_id' => $itemId,
                    'appraisal_status' => $appraisal->status->value,
                    'actor_employee_id' => $actorEmployee?->id,
                ]);
            }
        }
    }

    private function isAuthorizedForStageTransition(
        Request $request,
        Appraisal $appraisal,
        AppraisalStatus $targetStatus,
        ?Employee $actorEmployee
    ): bool {
        if ($actorEmployee === null) {
            return false;
        }

        $hasEvaluatePerm = $request->user()?->can('hrms.appraisal.evaluate') ?? false;

        return match ($targetStatus) {
            AppraisalStatus::SelfAssessment => $hasEvaluatePerm && $this->isReviewerOnAppraisal($appraisal, $actorEmployee),
            AppraisalStatus::SupervisorReview => $actorEmployee->id === $appraisal->employee_id,
            AppraisalStatus::HodReview => $hasEvaluatePerm && $actorEmployee->id === $appraisal->supervisor_id,
            AppraisalStatus::HrReview => $hasEvaluatePerm && $actorEmployee->id === $appraisal->hod_id,
            AppraisalStatus::Complete => $hasEvaluatePerm && $actorEmployee->id === $appraisal->hr_reviewer_id,
            AppraisalStatus::Draft, AppraisalStatus::Cancelled => false,
        };
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function logHrOverride(Request $request, Appraisal $appraisal, string $event, array $properties): void
    {
        activity('hrms-appraisal')
            ->performedOn($appraisal)
            ->causedBy($request->user())
            ->withProperties($properties)
            ->event($event)
            ->log("HR manager override: {$event}");
    }

    private function recalculateAndPersistScores(Appraisal $appraisal): void
    {
        $selfScore = $appraisal->calculateSelfScore();
        $supervisorScore = $appraisal->calculateSupervisorScore();
        $finalScore = $appraisal->calculateFinalScore();

        $appraisal->update([
            'self_overall_score' => $selfScore > 0 ? $selfScore : $appraisal->self_overall_score,
            'supervisor_overall_score' => $supervisorScore > 0 ? $supervisorScore : $appraisal->supervisor_overall_score,
            'final_overall_score' => $finalScore > 0 ? $finalScore : $appraisal->final_overall_score,
        ]);
    }

    private function seedDefaultScalesForPeriod(string $tenantId, int $periodId): void
    {
        $defaults = [
            ['value' => 1, 'label' => 'Unsatisfactory', 'description' => 'Performance consistently fails to meet minimum job requirements.'],
            ['value' => 2, 'label' => 'Below Expectations', 'description' => 'Performance does not fully meet job requirements in some areas.'],
            ['value' => 3, 'label' => 'Meets Expectations', 'description' => 'Performance consistently meets job requirements and expectations.'],
            ['value' => 4, 'label' => 'Exceeds Expectations', 'description' => 'Performance frequently exceeds job requirements.'],
            ['value' => 5, 'label' => 'Outstanding', 'description' => 'Performance consistently exceeds all job requirements with exceptional results.'],
        ];

        foreach ($defaults as $scale) {
            AppraisalRatingScale::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'period_id' => $periodId,
                    'value' => $scale['value'],
                ],
                [
                    'label' => $scale['label'],
                    'description' => $scale['description'],
                ]
            );
        }
    }

    private function uniquePeriodSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'appraisal-period';
        }

        if (! AppraisalPeriod::withoutGlobalScopes()->where('slug', $base)->exists()) {
            return $base;
        }

        for ($counter = 2; $counter <= 50; $counter++) {
            $candidate = "{$base}-{$counter}";
            if (! AppraisalPeriod::withoutGlobalScopes()->where('slug', $candidate)->exists()) {
                return $candidate;
            }
        }

        return $base.'-'.Str::lower(Str::random(8));
    }

    private function uniqueTemplateSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'appraisal-template';
        }

        if (! AppraisalTemplate::withoutGlobalScopes()->where('slug', $base)->exists()) {
            return $base;
        }

        for ($counter = 2; $counter <= 50; $counter++) {
            $candidate = "{$base}-{$counter}";
            if (! AppraisalTemplate::withoutGlobalScopes()->where('slug', $candidate)->exists()) {
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

    private function assertAppraisalMutable(Appraisal $appraisal): void
    {
        if ($appraisal->status->isFinal()) {
            throw ValidationException::withMessages([
                'appraisal' => "Cannot modify a {$appraisal->status->label()} appraisal.",
            ]);
        }
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
