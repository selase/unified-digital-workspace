<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\HrmsCore\Enums\ApplicationStatus;
use App\Modules\HrmsCore\Enums\InterviewStatus;
use App\Modules\HrmsCore\Enums\OfferStatus;
use App\Modules\HrmsCore\Enums\RequisitionStatus;
use App\Modules\HrmsCore\Http\Requests\Recruitment\JobPostingRequest;
use App\Modules\HrmsCore\Http\Requests\Recruitment\JobRequisitionRequest;
use App\Modules\HrmsCore\Http\Requests\Recruitment\OfferDecisionRequest;
use App\Modules\HrmsCore\Http\Requests\Recruitment\RequisitionStatusRequest;
use App\Modules\HrmsCore\Http\Requests\Recruitment\StoreCandidateApplicationRequest;
use App\Modules\HrmsCore\Http\Requests\Recruitment\StoreInterviewRequest;
use App\Modules\HrmsCore\Http\Requests\Recruitment\StoreJobOfferRequest;
use App\Modules\HrmsCore\Http\Requests\Recruitment\UpdateApplicationStatusRequest;
use App\Modules\HrmsCore\Http\Requests\Recruitment\UpdateInterviewRequest;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Organization\Grade;
use App\Modules\HrmsCore\Models\Recruitment\Candidate;
use App\Modules\HrmsCore\Models\Recruitment\CandidateApplication;
use App\Modules\HrmsCore\Models\Recruitment\Interview;
use App\Modules\HrmsCore\Models\Recruitment\JobOffer;
use App\Modules\HrmsCore\Models\Recruitment\JobPosting;
use App\Modules\HrmsCore\Models\Recruitment\JobRequisition;
use App\Modules\HrmsCore\Models\Recruitment\OnboardingTask;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class HrmsRecruitmentController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('hrms.jobs.view'), 403);

        $tenantId = $this->activeTenantId($request);
        $search = mb_trim((string) $request->string('search'));
        $status = (string) $request->string('status');
        $requisitionStatus = (string) $request->string('requisition_status');

        $jobPostings = JobPosting::query()
            ->where('tenant_id', $tenantId)
            ->with(['requisition:id,title,department_id,requisition_number,status', 'requisition.department:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%");
            })
            ->when(in_array($status, ['open', 'closed'], true), function ($query) use ($status): void {
                $query->where('is_active', $status === 'open');
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $requisitions = JobRequisition::query()
            ->where('tenant_id', $tenantId)
            ->with(['department:id,name', 'grade:id,name'])
            ->withCount('postings')
            ->when($requisitionStatus !== '', function ($query) use ($requisitionStatus): void {
                $query->where('status', $requisitionStatus);
            })
            ->latest('id')
            ->paginate(15, ['*'], 'req_page')
            ->withQueryString();

        $departments = Department::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $grades = Grade::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $employees = Employee::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'employee_staff_id']);

        $approvedOrOpenRequisitions = JobRequisition::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [RequisitionStatus::Approved, RequisitionStatus::Open])
            ->orderBy('title')
            ->get(['id', 'title', 'requisition_number']);

        return view('hrms-core::recruitment', [
            'jobPostings' => $jobPostings,
            'requisitions' => $requisitions,
            'departments' => $departments,
            'grades' => $grades,
            'employees' => $employees,
            'approvedOrOpenRequisitions' => $approvedOrOpenRequisitions,
            'requisitionStatuses' => RequisitionStatus::cases(),
            'search' => $search,
            'status' => $status,
            'requisitionStatus' => $requisitionStatus,
        ]);
    }

    public function showPosting(Request $request, JobPosting $posting): View
    {
        $user = $request->user();
        abort_if($user === null || (! $user->can('hrms.jobs.view') && ! $user->can('hrms.applications.view')), 403);
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($posting->tenant_id, $tenantId);

        $posting->load([
            'requisition.department:id,name',
            'requisition.grade:id,name',
            'applications' => function ($query): void {
                $query->with(['candidate', 'interviews', 'offer.department:id,name', 'offer.grade:id,name'])
                    ->latest('id');
            },
        ]);

        $departments = Department::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $grades = Grade::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $candidates = Candidate::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        return view('hrms-core::recruitment-show', [
            'posting' => $posting,
            'departments' => $departments,
            'grades' => $grades,
            'candidates' => $candidates,
            'applicationStatuses' => ApplicationStatus::cases(),
            'interviewStatuses' => InterviewStatus::cases(),
        ]);
    }

    public function storeRequisition(JobRequisitionRequest $request): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        $validated = $request->validated();

        $requisitionNumber = isset($validated['requisition_number']) && mb_trim((string) $validated['requisition_number']) !== ''
            ? mb_trim((string) $validated['requisition_number'])
            : 'REQ-'.now()->format('Y').'-'.mb_strtoupper(Str::random(5));

        JobRequisition::create([
            'tenant_id' => $tenantId,
            'title' => $validated['title'],
            'requisition_number' => $requisitionNumber,
            'department_id' => $validated['department_id'] ?? null,
            'grade_id' => $validated['grade_id'] ?? null,
            'requested_by' => $validated['requested_by'] ?? $this->resolveCurrentEmployeeId($request, $tenantId),
            'employment_type' => $validated['employment_type'],
            'vacancies' => (int) $validated['vacancies'],
            'min_salary' => $validated['min_salary'] ?? null,
            'max_salary' => $validated['max_salary'] ?? null,
            'location' => $validated['location'] ?? null,
            'is_remote' => $request->boolean('is_remote', false),
            'target_start_date' => $validated['target_start_date'] ?? null,
            'application_deadline' => $validated['application_deadline'] ?? null,
            'job_description' => $validated['job_description'] ?? null,
            'requirements' => $validated['requirements'] ?? null,
            'responsibilities' => $validated['responsibilities'] ?? null,
            'status' => RequisitionStatus::Draft,
        ]);

        return redirect()
            ->route('hrms-core.recruitment.index')
            ->with('status', 'Job requisition created successfully.');
    }

    public function updateRequisition(JobRequisitionRequest $request, JobRequisition $requisition): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($requisition->tenant_id, $tenantId);
        $validated = $request->validated();

        $requisition->update([
            'title' => $validated['title'],
            'requisition_number' => isset($validated['requisition_number']) && mb_trim((string) $validated['requisition_number']) !== ''
                ? mb_trim((string) $validated['requisition_number'])
                : $requisition->requisition_number,
            'department_id' => $validated['department_id'] ?? null,
            'grade_id' => $validated['grade_id'] ?? null,
            'requested_by' => $validated['requested_by'] ?? $requisition->requested_by,
            'employment_type' => $validated['employment_type'],
            'vacancies' => (int) $validated['vacancies'],
            'min_salary' => $validated['min_salary'] ?? null,
            'max_salary' => $validated['max_salary'] ?? null,
            'location' => $validated['location'] ?? null,
            'is_remote' => $request->boolean('is_remote', $requisition->is_remote),
            'target_start_date' => $validated['target_start_date'] ?? null,
            'application_deadline' => $validated['application_deadline'] ?? null,
            'job_description' => $validated['job_description'] ?? null,
            'requirements' => $validated['requirements'] ?? null,
            'responsibilities' => $validated['responsibilities'] ?? null,
        ]);

        return redirect()
            ->route('hrms-core.recruitment.index')
            ->with('status', 'Job requisition updated successfully.');
    }

    public function transitionRequisitionStatus(RequisitionStatusRequest $request, JobRequisition $requisition): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($requisition->tenant_id, $tenantId);

        $targetStatus = RequisitionStatus::from((string) $request->validated('status'));
        $currentStatus = $requisition->status;

        $transitioned = match ($targetStatus) {
            RequisitionStatus::PendingApproval => $currentStatus === RequisitionStatus::Draft
                && $requisition->update(['status' => RequisitionStatus::PendingApproval]),
            RequisitionStatus::Approved => in_array($currentStatus, [RequisitionStatus::Draft, RequisitionStatus::PendingApproval], true)
                && $requisition->update([
                    'status' => RequisitionStatus::Approved,
                    'approved_by' => $this->resolveCurrentEmployeeId($request, $tenantId),
                    'approved_at' => Carbon::now(),
                ]),
            RequisitionStatus::Open => $requisition->open(),
            RequisitionStatus::Closed => $requisition->close(),
            RequisitionStatus::Cancelled => ! $currentStatus->isFinal()
                && $requisition->update(['status' => RequisitionStatus::Cancelled]),
            default => false,
        };

        if (! $transitioned) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition job requisition from {$currentStatus->label()} to {$targetStatus->label()}.",
            ]);
        }

        return redirect()
            ->route('hrms-core.recruitment.index')
            ->with('status', "Requisition status updated to {$targetStatus->label()}.");
    }

    public function destroyRequisition(Request $request, JobRequisition $requisition): RedirectResponse
    {
        abort_if(! $request->user()?->can('hrms.jobs.manage'), 403);
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($requisition->tenant_id, $tenantId);

        $requisition->delete();

        return redirect()
            ->route('hrms-core.recruitment.index')
            ->with('status', 'Job requisition deleted.');
    }

    public function storePosting(JobPostingRequest $request): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        $validated = $request->validated();

        $slugBase = Str::slug($validated['title']);
        $slug = $slugBase.'-'.mb_strtolower(Str::random(6));

        $posting = JobPosting::create([
            'tenant_id' => $tenantId,
            'requisition_id' => (int) $validated['requisition_id'],
            'title' => $validated['title'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'requirements' => $validated['requirements'] ?? null,
            'benefits' => $validated['benefits'] ?? null,
            'is_internal' => $request->boolean('is_internal', false),
            'is_external' => $request->boolean('is_external', true),
            'is_active' => $request->boolean('is_active', true),
            'posted_date' => $validated['posted_date'] ?? Carbon::today()->toDateString(),
            'closing_date' => $validated['closing_date'] ?? null,
        ]);

        return redirect()
            ->route('hrms-core.recruitment.postings.show', $posting)
            ->with('status', 'Job posting published successfully.');
    }

    public function updatePosting(JobPostingRequest $request, JobPosting $posting): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($posting->tenant_id, $tenantId);
        $validated = $request->validated();

        $posting->update([
            'requisition_id' => (int) $validated['requisition_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'requirements' => $validated['requirements'] ?? null,
            'benefits' => $validated['benefits'] ?? null,
            'is_internal' => $request->boolean('is_internal', $posting->is_internal),
            'is_external' => $request->boolean('is_external', $posting->is_external),
            'is_active' => $request->boolean('is_active', $posting->is_active),
            'posted_date' => $validated['posted_date'] ?? $posting->posted_date?->toDateString(),
            'closing_date' => $validated['closing_date'] ?? null,
        ]);

        return redirect()
            ->route('hrms-core.recruitment.postings.show', $posting)
            ->with('status', 'Job posting updated successfully.');
    }

    public function togglePostingStatus(Request $request, JobPosting $posting): RedirectResponse
    {
        abort_if(! $request->user()?->can('hrms.jobs.manage'), 403);
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($posting->tenant_id, $tenantId);

        $posting->update([
            'is_active' => ! $posting->is_active,
        ]);

        return redirect()
            ->back()
            ->with('status', $posting->is_active ? 'Job posting re-opened.' : 'Job posting closed.');
    }

    public function destroyPosting(Request $request, JobPosting $posting): RedirectResponse
    {
        abort_if(! $request->user()?->can('hrms.jobs.manage'), 403);
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($posting->tenant_id, $tenantId);

        $posting->delete();

        return redirect()
            ->route('hrms-core.recruitment.index')
            ->with('status', 'Job posting deleted.');
    }

    public function storeApplication(StoreCandidateApplicationRequest $request, JobPosting $posting): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($posting->tenant_id, $tenantId);

        if (! $posting->isAcceptingApplications()) {
            throw ValidationException::withMessages([
                'posting' => 'This job posting is closed or expired and is no longer accepting applications.',
            ]);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $posting, $tenantId): void {
            if (! empty($validated['candidate_id'])) {
                $candidate = Candidate::query()
                    ->where('tenant_id', $tenantId)
                    ->findOrFail((int) $validated['candidate_id']);
            } else {
                $email = mb_strtolower(mb_trim((string) $validated['email']));
                $candidate = Candidate::query()
                    ->where('tenant_id', $tenantId)
                    ->where('email', $email)
                    ->first();

                if ($candidate === null) {
                    $candidate = Candidate::create([
                        'tenant_id' => $tenantId,
                        'first_name' => $validated['first_name'],
                        'last_name' => $validated['last_name'],
                        'email' => $email,
                        'phone' => $validated['phone'] ?? null,
                        'current_employer' => $validated['current_employer'] ?? null,
                        'current_position' => $validated['current_position'] ?? null,
                        'expected_salary' => $validated['expected_salary'] ?? null,
                        'years_of_experience' => isset($validated['years_of_experience']) ? (int) $validated['years_of_experience'] : null,
                        'highest_qualification' => $validated['highest_qualification'] ?? null,
                        'source' => $validated['source'] ?? 'direct',
                        'status' => 'active',
                    ]);
                }
            }

            if ($candidate->isBlacklisted()) {
                throw ValidationException::withMessages([
                    'email' => 'This candidate is blacklisted and cannot be submitted to active job postings.',
                ]);
            }

            $alreadyApplied = CandidateApplication::withTrashed()
                ->where('candidate_id', $candidate->id)
                ->where('posting_id', $posting->id)
                ->exists();

            if ($alreadyApplied) {
                throw ValidationException::withMessages([
                    'email' => 'This candidate has already applied for this job posting.',
                ]);
            }

            CandidateApplication::create([
                'tenant_id' => $tenantId,
                'candidate_id' => $candidate->id,
                'posting_id' => $posting->id,
                'application_number' => 'APP-'.now()->format('Y').'-'.mb_strtoupper(Str::random(5)),
                'status' => ApplicationStatus::Submitted,
                'stage' => 'application',
                'cover_letter' => $validated['cover_letter'] ?? null,
                'proposed_start_date' => $validated['proposed_start_date'] ?? null,
            ]);

            $posting->incrementApplications();
        });

        return redirect()
            ->route('hrms-core.recruitment.postings.show', $posting)
            ->with('status', 'Candidate application recorded successfully.');
    }

    public function updateApplicationStatus(
        UpdateApplicationStatusRequest $request,
        CandidateApplication $application
    ): RedirectResponse {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($application->tenant_id, $tenantId);
        $validated = $request->validated();

        $targetStatus = ApplicationStatus::from((string) $validated['status']);
        $currentStatus = $application->status;

        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition application from {$currentStatus->label()} to {$targetStatus->label()}.",
            ]);
        }

        if ($targetStatus === ApplicationStatus::Hired) {
            $offer = $application->offer;
            if ($offer === null || $offer->status !== OfferStatus::Accepted) {
                throw ValidationException::withMessages([
                    'status' => 'An application can only be marked as Hired after a job offer has been accepted.',
                ]);
            }
        }

        $employeeId = $this->resolveCurrentEmployeeId($request, $tenantId);

        DB::transaction(function () use ($application, $targetStatus, $validated, $request, $employeeId, $tenantId): void {
            $updates = [
                'status' => $targetStatus,
            ];

            if (! empty($validated['stage'])) {
                $updates['stage'] = $validated['stage'];
            } else {
                $updates['stage'] = $targetStatus->value;
            }

            if (array_key_exists('screening_notes', $validated) && $validated['screening_notes'] !== null) {
                $updates['screening_notes'] = $validated['screening_notes'];
                $updates['screened_by'] = $employeeId;
                $updates['screened_at'] = Carbon::now();
            }

            if ($request->has('is_recommended')) {
                $updates['is_recommended'] = $request->boolean('is_recommended');
            }

            if ($targetStatus === ApplicationStatus::Rejected) {
                $updates['rejected_at'] = Carbon::now();
                $updates['rejected_by'] = $employeeId;
                $updates['rejection_reason'] = $validated['rejection_reason'] ?? null;
            }

            if ($targetStatus === ApplicationStatus::Hired) {
                $updates['hired_at'] = Carbon::now();
                $updates['hired_by'] = $employeeId;
                $application->candidate?->markHired();
                $this->provisionHiredEmployeeAndOnboarding($application, $application->offer, $tenantId);
            }

            $application->update($updates);
        });

        return redirect()
            ->route('hrms-core.recruitment.postings.show', $application->posting)
            ->with('status', "Application moved to {$targetStatus->label()}.");
    }

    public function destroyApplication(Request $request, CandidateApplication $application): RedirectResponse
    {
        abort_if(! $request->user()?->can('hrms.applications.manage'), 403);
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($application->tenant_id, $tenantId);

        $posting = $application->posting;

        DB::transaction(function () use ($application, $posting): void {
            $application->delete();

            if ($posting !== null && $posting->applications_count > 0) {
                $posting->decrement('applications_count');
            }
        });

        return redirect()
            ->route('hrms-core.recruitment.postings.show', $posting)
            ->with('status', 'Candidate application removed.');
    }

    public function storeInterview(StoreInterviewRequest $request, CandidateApplication $application): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($application->tenant_id, $tenantId);
        $validated = $request->validated();

        if ($application->status->isFinal()) {
            throw ValidationException::withMessages([
                'interview' => "Cannot schedule an interview for an application in {$application->status->label()} status.",
            ]);
        }

        DB::transaction(function () use ($application, $validated, $request, $tenantId): void {
            Interview::create([
                'tenant_id' => $tenantId,
                'application_id' => $application->id,
                'type' => $validated['type'],
                'interview_date' => $validated['interview_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'] ?? null,
                'location' => $validated['location'] ?? null,
                'meeting_link' => $validated['meeting_link'] ?? null,
                'instructions' => $validated['instructions'] ?? null,
                'status' => InterviewStatus::Scheduled,
                'scheduled_by' => $this->resolveCurrentEmployeeId($request, $tenantId),
            ]);

            if (in_array($application->status, [
                ApplicationStatus::Submitted,
                ApplicationStatus::Screening,
                ApplicationStatus::Shortlisted,
            ], true)) {
                $application->moveToInterview();
            }
        });

        return redirect()
            ->route('hrms-core.recruitment.postings.show', $application->posting)
            ->with('status', 'Interview scheduled successfully.');
    }

    public function updateInterview(UpdateInterviewRequest $request, Interview $interview): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($interview->tenant_id, $tenantId);
        $validated = $request->validated();

        $targetStatus = InterviewStatus::from((string) $validated['status']);

        $updates = [
            'status' => $targetStatus,
            'type' => $validated['type'] ?? $interview->type,
            'interview_date' => $validated['interview_date'] ?? $interview->interview_date->toDateString(),
            'start_time' => $validated['start_time'] ?? $interview->start_time,
            'end_time' => $validated['end_time'] ?? $interview->end_time,
            'location' => $validated['location'] ?? $interview->location,
            'meeting_link' => $validated['meeting_link'] ?? $interview->meeting_link,
            'feedback_summary' => $validated['feedback_summary'] ?? $interview->feedback_summary,
            'overall_rating' => $validated['overall_rating'] ?? $interview->overall_rating,
        ];

        if ($request->has('is_recommended')) {
            $updates['is_recommended'] = $request->boolean('is_recommended');
        }

        if ($targetStatus === InterviewStatus::Confirmed && $interview->confirmed_at === null) {
            $updates['confirmed_at'] = Carbon::now();
        }

        if (in_array($targetStatus, [InterviewStatus::Completed, InterviewStatus::NoShow], true)) {
            $updates['completed_at'] = Carbon::now();
        }

        if ($targetStatus === InterviewStatus::Cancelled) {
            $updates['cancelled_at'] = Carbon::now();
            $updates['cancellation_reason'] = $validated['cancellation_reason'] ?? null;
        }

        $interview->update($updates);

        $posting = $interview->application?->posting;

        return redirect()
            ->route('hrms-core.recruitment.postings.show', $posting)
            ->with('status', 'Interview updated successfully.');
    }

    public function destroyInterview(Request $request, Interview $interview): RedirectResponse
    {
        abort_if(! $request->user()?->can('hrms.applications.manage'), 403);
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($interview->tenant_id, $tenantId);

        $posting = $interview->application?->posting;
        $interview->delete();

        return redirect()
            ->route('hrms-core.recruitment.postings.show', $posting)
            ->with('status', 'Interview deleted.');
    }

    public function storeOffer(StoreJobOfferRequest $request, CandidateApplication $application): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($application->tenant_id, $tenantId);
        $validated = $request->validated();

        if ($application->status !== ApplicationStatus::Offer && ! $application->status->canTransitionTo(ApplicationStatus::Offer)) {
            throw ValidationException::withMessages([
                'offer' => "Cannot prepare a job offer while application is in {$application->status->label()} status.",
            ]);
        }

        DB::transaction(function () use ($application, $validated, $tenantId): void {
            $existingOffer = JobOffer::query()
                ->where('tenant_id', $tenantId)
                ->where('application_id', $application->id)
                ->first();

            if ($existingOffer !== null && ! $existingOffer->status->canModify()) {
                throw ValidationException::withMessages([
                    'offer' => 'An offer has already been sent or finalized for this application.',
                ]);
            }

            $offerNumber = $existingOffer !== null && $existingOffer->offer_number !== null
                ? $existingOffer->offer_number
                : 'OFR-'.now()->format('Y').'-'.mb_strtoupper(Str::random(5));

            JobOffer::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'application_id' => $application->id,
                ],
                [
                    'offer_number' => $offerNumber,
                    'position_title' => $validated['position_title'],
                    'department_id' => $validated['department_id'] ?? $application->posting?->requisition?->department_id,
                    'grade_id' => $validated['grade_id'] ?? $application->posting?->requisition?->grade_id,
                    'employment_type' => $validated['employment_type'],
                    'offered_salary' => $validated['offered_salary'],
                    'start_date' => $validated['start_date'],
                    'offer_valid_until' => $validated['offer_valid_until'] ?? null,
                    'additional_terms' => $validated['additional_terms'] ?? null,
                    'status' => OfferStatus::Draft,
                ]
            );

            $application->update([
                'status' => ApplicationStatus::Offer,
                'stage' => 'offer',
                'offered_salary' => $validated['offered_salary'],
                'proposed_start_date' => $validated['start_date'],
            ]);
        });

        return redirect()
            ->route('hrms-core.recruitment.postings.show', $application->posting)
            ->with('status', 'Job offer saved in draft.');
    }

    public function sendOffer(Request $request, JobOffer $offer): RedirectResponse
    {
        abort_if(! $request->user()?->can('hrms.applications.manage'), 403);
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($offer->tenant_id, $tenantId);

        if (! $offer->status->canTransitionTo(OfferStatus::Sent)) {
            throw ValidationException::withMessages([
                'offer' => "Cannot transition offer from {$offer->status->label()} to Sent.",
            ]);
        }

        $offer->update([
            'approved_by' => $this->resolveCurrentEmployeeId($request, $tenantId),
            'approved_at' => Carbon::now(),
            'sent_at' => Carbon::now(),
            'status' => OfferStatus::Sent,
        ]);

        return redirect()
            ->route('hrms-core.recruitment.postings.show', $offer->application?->posting)
            ->with('status', 'Job offer approved and dispatched to candidate.');
    }

    public function recordOfferDecision(OfferDecisionRequest $request, JobOffer $offer): RedirectResponse
    {
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($offer->tenant_id, $tenantId);
        $validated = $request->validated();

        $decision = (string) $validated['decision'];
        $feedback = isset($validated['candidate_feedback']) ? (string) $validated['candidate_feedback'] : null;

        $targetOfferStatus = match ($decision) {
            'accept' => OfferStatus::Accepted,
            'reject' => OfferStatus::Rejected,
            default => OfferStatus::Withdrawn,
        };

        if (! $offer->status->canTransitionTo($targetOfferStatus)) {
            throw ValidationException::withMessages([
                'decision' => "Cannot transition offer from {$offer->status->label()} to {$targetOfferStatus->label()}.",
            ]);
        }

        DB::transaction(function () use ($offer, $decision, $feedback, $request, $tenantId): void {
            if ($decision === 'accept') {
                $offer->accept($feedback);

                $application = $offer->application;
                if ($application !== null) {
                    $application->update([
                        'status' => ApplicationStatus::Hired,
                        'stage' => 'hired',
                        'hired_at' => Carbon::now(),
                        'hired_by' => $this->resolveCurrentEmployeeId($request, $tenantId),
                    ]);
                    $application->candidate?->markHired();
                    $this->provisionHiredEmployeeAndOnboarding($application, $offer, $tenantId);
                }
            } elseif ($decision === 'reject') {
                $offer->reject($feedback ?? 'Declined by candidate');
            } elseif ($decision === 'withdraw') {
                $offer->withdraw();
            }
        });

        return redirect()
            ->route('hrms-core.recruitment.postings.show', $offer->application?->posting)
            ->with('status', 'Job offer decision recorded.');
    }

    public function destroyOffer(Request $request, JobOffer $offer): RedirectResponse
    {
        abort_if(! $request->user()?->can('hrms.applications.manage'), 403);
        $tenantId = $this->activeTenantId($request);
        $this->assertTenantModel($offer->tenant_id, $tenantId);

        $posting = $offer->application?->posting;
        $offer->delete();

        return redirect()
            ->route('hrms-core.recruitment.postings.show', $posting)
            ->with('status', 'Job offer deleted.');
    }

    private function provisionHiredEmployeeAndOnboarding(
        CandidateApplication $application,
        ?JobOffer $offer,
        string $tenantId
    ): void {
        $candidate = $application->candidate;
        if ($candidate === null) {
            return;
        }

        $employee = null;
        if ($candidate->email !== null && $candidate->email !== '') {
            $employee = Employee::query()
                ->where('tenant_id', $tenantId)
                ->where('email', $candidate->email)
                ->first();
        }

        $gradeId = $offer !== null && $offer->grade_id !== null
            ? $offer->grade_id
            : $application->posting?->requisition?->grade_id;

        if ($employee === null) {
            $employee = Employee::create([
                'tenant_id' => $tenantId,
                'first_name' => $candidate->first_name,
                'last_name' => $candidate->last_name,
                'email' => $candidate->email,
                'mobile' => $candidate->phone,
                'grade_id' => $gradeId,
                'is_active' => true,
            ]);
        }

        $departmentId = $offer !== null && $offer->department_id !== null
            ? $offer->department_id
            : $application->posting?->requisition?->department_id;
        if ($departmentId !== null) {
            $employee->departments()->syncWithoutDetaching([$departmentId]);
        }

        $baseDueDate = $offer !== null && $offer->start_date !== null
            ? $offer->start_date
            : Carbon::today()->addWeeks(2);
        $defaultTasks = [
            [
                'sequence' => 1,
                'category' => OnboardingTask::CATEGORY_DOCUMENTATION,
                'name' => 'Verify employment contract, tax, and identity documents',
            ],
            [
                'sequence' => 2,
                'category' => OnboardingTask::CATEGORY_EQUIPMENT,
                'name' => 'Provision workstation and staff identification badge',
            ],
            [
                'sequence' => 3,
                'category' => OnboardingTask::CATEGORY_ACCESS,
                'name' => 'Configure institutional workspace and email accounts',
            ],
            [
                'sequence' => 4,
                'category' => OnboardingTask::CATEGORY_ORIENTATION,
                'name' => 'Complete departmental induction and safety briefing',
            ],
        ];

        foreach ($defaultTasks as $taskData) {
            OnboardingTask::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'employee_id' => $employee->id,
                    'application_id' => $application->id,
                    'sequence' => $taskData['sequence'],
                ],
                [
                    'name' => $taskData['name'],
                    'category' => $taskData['category'],
                    'status' => 'pending',
                    'due_date' => $baseDueDate->copy()->addDays($taskData['sequence'] * 2)->toDateString(),
                ]
            );
        }
    }

    private function activeTenantId(Request $request): string
    {
        $tenantId = (string) (session('active_tenant_id') ?? $request->user()?->tenant_id);
        abort_if($tenantId === '', 403);

        return $tenantId;
    }

    private function assertTenantModel(mixed $modelTenantId, string $activeTenantId): void
    {
        abort_if((string) $modelTenantId !== $activeTenantId, 404);
    }

    private function resolveCurrentEmployeeId(Request $request, string $tenantId): ?int
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $employeeId = Employee::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->value('id');

        return $employeeId !== null ? (int) $employeeId : null;
    }
}
