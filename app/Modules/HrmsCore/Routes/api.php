<?php

declare(strict_types=1);

use App\Modules\HrmsCore\Http\Controllers\Api\V1\ReadOnlyResourceController;
use App\Modules\HrmsCore\Http\Resources\AppraisalCriterionResource;
use App\Modules\HrmsCore\Http\Resources\AppraisalGoalResource;
use App\Modules\HrmsCore\Http\Resources\AppraisalPeriodResource;
use App\Modules\HrmsCore\Http\Resources\AppraisalRecommendationResource;
use App\Modules\HrmsCore\Http\Resources\AppraisalResource;
use App\Modules\HrmsCore\Http\Resources\AppraisalResponseResource;
use App\Modules\HrmsCore\Http\Resources\AppraisalReviewResource;
use App\Modules\HrmsCore\Http\Resources\AppraisalScoreResource;
use App\Modules\HrmsCore\Http\Resources\AppraisalSectionResource;
use App\Modules\HrmsCore\Http\Resources\AppraisalTemplateResource;
use App\Modules\HrmsCore\Http\Resources\CandidateApplicationResource;
use App\Modules\HrmsCore\Http\Resources\CandidateAssessmentResource;
use App\Modules\HrmsCore\Http\Resources\CandidateDocumentResource;
use App\Modules\HrmsCore\Http\Resources\CandidateReferenceResource;
use App\Modules\HrmsCore\Http\Resources\CandidateResource;
use App\Modules\HrmsCore\Http\Resources\DepartmentResource;
use App\Modules\HrmsCore\Http\Resources\EmployeeResource;
use App\Modules\HrmsCore\Http\Resources\GradeResource;
use App\Modules\HrmsCore\Http\Resources\InterviewEvaluationResource;
use App\Modules\HrmsCore\Http\Resources\InterviewPanelResource;
use App\Modules\HrmsCore\Http\Resources\InterviewResource;
use App\Modules\HrmsCore\Http\Resources\InterviewStageResource;
use App\Modules\HrmsCore\Http\Resources\JobOfferResource;
use App\Modules\HrmsCore\Http\Resources\JobPostingResource;
use App\Modules\HrmsCore\Http\Resources\JobRequisitionResource;
use App\Modules\HrmsCore\Http\Resources\LeaveBalanceResource;
use App\Modules\HrmsCore\Http\Resources\LeaveCategoryResource;
use App\Modules\HrmsCore\Http\Resources\LeaveRequestResource;
use App\Modules\HrmsCore\Http\Resources\OfferNegotiationResource;
use App\Modules\HrmsCore\Http\Resources\OnboardingTaskResource;
use App\Modules\HrmsCore\Http\Resources\SalaryLevelResource;
use App\Modules\HrmsCore\Http\Resources\SalaryStepResource;
use App\Modules\HrmsCore\Http\Resources\StaffPromotionResource;
use App\Modules\HrmsCore\Models\Appraisal\Appraisal;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalCriterion;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalGoal;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalPeriod;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalRecommendation;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalResponse;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalReview;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalScore;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalSection;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalTemplate;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Leave\LeaveBalance;
use App\Modules\HrmsCore\Models\Leave\LeaveCategory;
use App\Modules\HrmsCore\Models\Leave\LeaveRequest;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Organization\Grade;
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
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HRMS Core API Routes
|--------------------------------------------------------------------------
|
| Routes are automatically prefixed with 'api/hrms-core' and use the
| 'api.hrms-core.' route name prefix.
|
| Middleware applied: api, auth:sanctum, module:hrms-core
|
*/

Route::get('/', function () {
    return response()->json([
        'module' => 'hrms-core',
        'version' => config('modules.hrms-core.version', '1.0.0'),
        'status' => 'active',
    ]);
})->name('index');

Route::prefix('v1')->name('v1.')->group(function (): void {
    $registerReadOnly = function (string $uri, string $model, string $resource, array $with = [], array $withCount = []): void {
        $name = str_replace('/', '.', $uri);

        Route::get($uri, [ReadOnlyResourceController::class, 'index'])
            ->defaults('model', $model)
            ->defaults('resource', $resource)
            ->defaults('with', $with)
            ->defaults('withCount', $withCount)
            ->name("{$name}.index");

        Route::get("{$uri}/{id}", [ReadOnlyResourceController::class, 'show'])
            ->defaults('model', $model)
            ->defaults('resource', $resource)
            ->defaults('with', $with)
            ->defaults('withCount', $withCount)
            ->name("{$name}.show");
    };

    $registerReadOnly('employees', Employee::class, EmployeeResource::class, ['grade', 'currentJob', 'departments']);
    $registerReadOnly('departments', Department::class, DepartmentResource::class, ['parent', 'children'], ['employees']);
    $registerReadOnly('grades', Grade::class, GradeResource::class, ['salaryLevel']);
    $registerReadOnly('salary-levels', SalaryLevel::class, SalaryLevelResource::class, ['grade']);
    $registerReadOnly('salary-steps', SalaryStep::class, SalaryStepResource::class, ['salaryLevel']);

    $registerReadOnly('leave-categories', LeaveCategory::class, LeaveCategoryResource::class);
    $registerReadOnly('leave-requests', LeaveRequest::class, LeaveRequestResource::class, [
        'employee',
        'leaveCategory',
        'supervisor',
        'hod',
        'hrVerifier',
        'relievingOfficer',
    ]);
    $registerReadOnly('leave-balances', LeaveBalance::class, LeaveBalanceResource::class, ['employee', 'leaveCategory']);

    $registerReadOnly('appraisals', Appraisal::class, AppraisalResource::class, [
        'employee',
        'period',
        'template',
        'responses',
        'goals',
        'reviews',
        'scores',
        'recommendations',
    ]);
    $registerReadOnly('appraisal-periods', AppraisalPeriod::class, AppraisalPeriodResource::class);
    $registerReadOnly('appraisal-templates', AppraisalTemplate::class, AppraisalTemplateResource::class, ['period']);
    $registerReadOnly('appraisal-sections', AppraisalSection::class, AppraisalSectionResource::class, ['template']);
    $registerReadOnly('appraisal-criteria', AppraisalCriterion::class, AppraisalCriterionResource::class, ['section']);
    $registerReadOnly('appraisal-responses', AppraisalResponse::class, AppraisalResponseResource::class, ['appraisal', 'criterion']);
    $registerReadOnly('appraisal-goals', AppraisalGoal::class, AppraisalGoalResource::class, ['appraisal']);
    $registerReadOnly('appraisal-reviews', AppraisalReview::class, AppraisalReviewResource::class, ['appraisal', 'reviewer']);
    $registerReadOnly('appraisal-scores', AppraisalScore::class, AppraisalScoreResource::class, ['appraisal', 'section']);
    $registerReadOnly('appraisal-recommendations', AppraisalRecommendation::class, AppraisalRecommendationResource::class, ['appraisal', 'recommendedBy', 'approvedBy']);

    $registerReadOnly('promotions', StaffPromotion::class, StaffPromotionResource::class, ['employee', 'supervisor', 'hrApprover']);

    $registerReadOnly('job-requisitions', JobRequisition::class, JobRequisitionResource::class, ['department', 'grade', 'requestedBy', 'approvedBy', 'postings']);
    $registerReadOnly('job-postings', JobPosting::class, JobPostingResource::class, ['requisition'], ['applications']);
    $registerReadOnly('candidates', Candidate::class, CandidateResource::class, ['applications', 'documents', 'references']);
    $registerReadOnly('candidate-applications', CandidateApplication::class, CandidateApplicationResource::class, ['candidate', 'posting', 'interviews', 'assessments', 'offer']);
    $registerReadOnly('candidate-documents', CandidateDocument::class, CandidateDocumentResource::class, ['candidate']);
    $registerReadOnly('interview-stages', InterviewStage::class, InterviewStageResource::class);
    $registerReadOnly('interviews', Interview::class, InterviewResource::class, ['application', 'stage', 'panelMembers', 'evaluations']);
    $registerReadOnly('interview-panels', InterviewPanel::class, InterviewPanelResource::class, ['interview', 'employee']);
    $registerReadOnly('interview-evaluations', InterviewEvaluation::class, InterviewEvaluationResource::class, ['interview', 'evaluator']);
    $registerReadOnly('candidate-assessments', CandidateAssessment::class, CandidateAssessmentResource::class, ['application', 'evaluatedBy']);
    $registerReadOnly('candidate-references', CandidateReference::class, CandidateReferenceResource::class, ['candidate']);
    $registerReadOnly('job-offers', JobOffer::class, JobOfferResource::class, ['application']);
    $registerReadOnly('offer-negotiations', OfferNegotiation::class, OfferNegotiationResource::class, ['offer']);
    $registerReadOnly('onboarding-tasks', OnboardingTask::class, OnboardingTaskResource::class, ['application', 'assignedTo', 'completedBy']);
});
