<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Modules\HrmsCore\Enums\ApplicationStatus;
use App\Modules\HrmsCore\Enums\InterviewStatus;
use App\Modules\HrmsCore\Enums\OfferStatus;
use App\Modules\HrmsCore\Enums\RequisitionStatus;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Organization\Department;
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
use App\Services\Tenancy\TenantContext;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Recruitment Test Company',
        'slug' => 'recruitment-test-company',
    ]);
    app(TenantContext::class)->setTenant($this->tenant);
    $this->employee = Employee::factory()->forTenant($this->tenant->id)->create();
    $this->department = Department::create(['name' => 'Engineering', 'slug' => 'engineering']);
});

// ==================== Enums Tests ====================

describe('RequisitionStatus Enum', function (): void {
    test('has all expected statuses', function (): void {
        expect(RequisitionStatus::cases())->toHaveCount(6)
            ->and(RequisitionStatus::Draft->value)->toBe('draft')
            ->and(RequisitionStatus::Open->value)->toBe('open');
    });

    test('provides labels and CSS classes', function (): void {
        expect(RequisitionStatus::Open->label())->toBe('Open')
            ->and(RequisitionStatus::Open->cssClass())->toContain('green');
    });

    test('identifies final statuses', function (): void {
        expect(RequisitionStatus::Closed->isFinal())->toBeTrue()
            ->and(RequisitionStatus::Open->isFinal())->toBeFalse();
    });
});

describe('ApplicationStatus Enum', function (): void {
    test('has all expected statuses', function (): void {
        expect(ApplicationStatus::cases())->toHaveCount(9)
            ->and(ApplicationStatus::Submitted->value)->toBe('submitted')
            ->and(ApplicationStatus::Hired->value)->toBe('hired');
    });

    test('identifies active applications', function (): void {
        expect(ApplicationStatus::Interview->isActive())->toBeTrue()
            ->and(ApplicationStatus::Hired->isActive())->toBeFalse();
    });
});

describe('InterviewStatus Enum', function (): void {
    test('has all expected statuses', function (): void {
        expect(InterviewStatus::cases())->toHaveCount(5);
    });

    test('identifies reschedule eligibility', function (): void {
        expect(InterviewStatus::Scheduled->canReschedule())->toBeTrue()
            ->and(InterviewStatus::Completed->canReschedule())->toBeFalse();
    });
});

describe('OfferStatus Enum', function (): void {
    test('has all expected statuses', function (): void {
        expect(OfferStatus::cases())->toHaveCount(7);
    });

    test('identifies modifiable offers', function (): void {
        expect(OfferStatus::Draft->canModify())->toBeTrue()
            ->and(OfferStatus::Sent->canModify())->toBeFalse();
    });
});

// ==================== Model Tests ====================

describe('JobRequisition Model', function (): void {
    test('can create a job requisition', function (): void {
        $requisition = JobRequisition::create([
            'title' => 'Senior Developer',
            'department_id' => $this->department->id,
            'requested_by' => $this->employee->id,
            'job_description' => 'We need a senior developer',
            'vacancies' => 2,
        ]);

        expect($requisition)->toBeInstanceOf(JobRequisition::class)
            ->and($requisition->uuid)->not->toBeNull()
            ->and($requisition->status)->toBe(RequisitionStatus::Draft)
            ->and($requisition->vacancies)->toBe(2);
    });

    test('has department relationship', function (): void {
        $requisition = JobRequisition::create([
            'title' => 'Developer',
            'department_id' => $this->department->id,
        ]);

        expect($requisition->department->id)->toBe($this->department->id);
    });

    test('can transition through approval workflow', function (): void {
        $requisition = JobRequisition::create([
            'title' => 'Developer',
            'status' => RequisitionStatus::PendingApproval,
        ]);

        expect($requisition->approve($this->employee->id))->toBeTrue()
            ->and($requisition->status)->toBe(RequisitionStatus::Approved);

        expect($requisition->open())->toBeTrue()
            ->and($requisition->isOpen())->toBeTrue();

        expect($requisition->close())->toBeTrue()
            ->and($requisition->isClosed())->toBeTrue();
    });
});

describe('JobPosting Model', function (): void {
    beforeEach(function (): void {
        $this->requisition = JobRequisition::create(['title' => 'Developer']);
    });

    test('can create a job posting', function (): void {
        $posting = JobPosting::create([
            'requisition_id' => $this->requisition->id,
            'title' => 'Senior Developer',
            'description' => 'Join our team',
            'is_active' => true,
        ]);

        expect($posting)->toBeInstanceOf(JobPosting::class)
            ->and($posting->is_active)->toBeTrue();
    });

    test('can check if accepting applications', function (): void {
        $posting = JobPosting::create([
            'requisition_id' => $this->requisition->id,
            'title' => 'Developer',
            'is_active' => true,
            'closing_date' => now()->addDays(30),
        ]);

        expect($posting->isAcceptingApplications())->toBeTrue();

        $expiredPosting = JobPosting::create([
            'requisition_id' => $this->requisition->id,
            'title' => 'Expired Job',
            'is_active' => true,
            'closing_date' => now()->subDay(),
        ]);

        expect($expiredPosting->isExpired())->toBeTrue()
            ->and($expiredPosting->isAcceptingApplications())->toBeFalse();
    });
});

describe('Candidate Model', function (): void {
    test('can create a candidate', function (): void {
        $candidate = Candidate::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
            'skills' => ['PHP', 'Laravel', 'Vue.js'],
        ]);

        expect($candidate)->toBeInstanceOf(Candidate::class)
            ->and($candidate->full_name)->toBe('John Doe')
            ->and($candidate->skills)->toContain('Laravel')
            ->and($candidate->isActive())->toBeTrue();
    });

    test('can blacklist candidate', function (): void {
        $candidate = Candidate::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $candidate->blacklist();

        expect($candidate->isBlacklisted())->toBeTrue();
    });
});

describe('CandidateApplication Model', function (): void {
    beforeEach(function (): void {
        $this->requisition = JobRequisition::create(['title' => 'Developer']);
        $this->posting = JobPosting::create([
            'requisition_id' => $this->requisition->id,
            'title' => 'Developer',
        ]);
        $this->candidate = Candidate::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
    });

    test('can create an application', function (): void {
        $application = CandidateApplication::create([
            'candidate_id' => $this->candidate->id,
            'posting_id' => $this->posting->id,
            'cover_letter' => 'I am interested in this position',
        ]);

        expect($application)->toBeInstanceOf(CandidateApplication::class)
            ->and($application->status)->toBe(ApplicationStatus::Submitted)
            ->and($application->isActive())->toBeTrue();
    });

    test('can transition application status', function (): void {
        $application = CandidateApplication::create([
            'candidate_id' => $this->candidate->id,
            'posting_id' => $this->posting->id,
        ]);

        expect($application->shortlist())->toBeTrue()
            ->and($application->status)->toBe(ApplicationStatus::Shortlisted);

        expect($application->moveToInterview())->toBeTrue()
            ->and($application->status)->toBe(ApplicationStatus::Interview);
    });

    test('can reject application', function (): void {
        $application = CandidateApplication::create([
            'candidate_id' => $this->candidate->id,
            'posting_id' => $this->posting->id,
        ]);

        expect($application->reject($this->employee->id, 'Not qualified'))->toBeTrue()
            ->and($application->status)->toBe(ApplicationStatus::Rejected)
            ->and($application->rejection_reason)->toBe('Not qualified');
    });

    test('can hire candidate', function (): void {
        $application = CandidateApplication::create([
            'candidate_id' => $this->candidate->id,
            'posting_id' => $this->posting->id,
        ]);

        expect($application->hire($this->employee->id))->toBeTrue()
            ->and($application->status)->toBe(ApplicationStatus::Hired)
            ->and($application->hired_at)->not->toBeNull();
    });
});

describe('CandidateDocument Model', function (): void {
    test('can create a document', function (): void {
        $candidate = Candidate::create(['first_name' => 'John', 'last_name' => 'Doe']);

        $document = CandidateDocument::create([
            'candidate_id' => $candidate->id,
            'type' => CandidateDocument::TYPE_RESUME,
            'name' => 'john_doe_resume.pdf',
            'file_path' => '/documents/resumes/john_doe.pdf',
            'is_primary' => true,
        ]);

        expect($document->isResume())->toBeTrue()
            ->and($document->is_primary)->toBeTrue();
    });
});

describe('Interview Model', function (): void {
    beforeEach(function (): void {
        $requisition = JobRequisition::create(['title' => 'Developer']);
        $posting = JobPosting::create(['requisition_id' => $requisition->id, 'title' => 'Developer']);
        $candidate = Candidate::create(['first_name' => 'John', 'last_name' => 'Doe']);
        $this->application = CandidateApplication::create([
            'candidate_id' => $candidate->id,
            'posting_id' => $posting->id,
        ]);
    });

    test('can create an interview', function (): void {
        $interview = Interview::create([
            'application_id' => $this->application->id,
            'type' => Interview::TYPE_VIDEO,
            'interview_date' => now()->addDays(7),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'meeting_link' => 'https://zoom.us/j/123456',
        ]);

        expect($interview)->toBeInstanceOf(Interview::class)
            ->and($interview->status)->toBe(InterviewStatus::Scheduled)
            ->and($interview->isScheduled())->toBeTrue();
    });

    test('can confirm interview', function (): void {
        $interview = Interview::create([
            'application_id' => $this->application->id,
            'interview_date' => now()->addDays(7),
            'start_time' => '10:00',
        ]);

        expect($interview->confirm())->toBeTrue()
            ->and($interview->isConfirmed())->toBeTrue()
            ->and($interview->confirmed_at)->not->toBeNull();
    });

    test('can complete interview', function (): void {
        $interview = Interview::create([
            'application_id' => $this->application->id,
            'interview_date' => now()->addDays(7),
            'start_time' => '10:00',
            'status' => InterviewStatus::Confirmed,
        ]);

        expect($interview->complete('Great candidate', true))->toBeTrue()
            ->and($interview->isCompleted())->toBeTrue()
            ->and($interview->is_recommended)->toBeTrue();
    });

    test('can cancel interview', function (): void {
        $interview = Interview::create([
            'application_id' => $this->application->id,
            'interview_date' => now()->addDays(7),
            'start_time' => '10:00',
        ]);

        expect($interview->cancel('Scheduling conflict'))->toBeTrue()
            ->and($interview->status)->toBe(InterviewStatus::Cancelled)
            ->and($interview->cancellation_reason)->toBe('Scheduling conflict');
    });
});

describe('InterviewPanel Model', function (): void {
    test('can create panel member', function (): void {
        $requisition = JobRequisition::create(['title' => 'Developer']);
        $posting = JobPosting::create(['requisition_id' => $requisition->id, 'title' => 'Developer']);
        $candidate = Candidate::create(['first_name' => 'John', 'last_name' => 'Doe']);
        $application = CandidateApplication::create([
            'candidate_id' => $candidate->id,
            'posting_id' => $posting->id,
        ]);
        $interview = Interview::create([
            'application_id' => $application->id,
            'interview_date' => now()->addDays(7),
            'start_time' => '10:00',
        ]);

        $panel = InterviewPanel::create([
            'interview_id' => $interview->id,
            'employee_id' => $this->employee->id,
            'role' => InterviewPanel::ROLE_LEAD,
        ]);

        expect($panel->isLead())->toBeTrue()
            ->and($panel->attendance_status)->toBe('pending');

        $panel->confirm();
        expect($panel->attendance_status)->toBe('confirmed');
    });
});

describe('InterviewEvaluation Model', function (): void {
    test('can create and submit evaluation', function (): void {
        $requisition = JobRequisition::create(['title' => 'Developer']);
        $posting = JobPosting::create(['requisition_id' => $requisition->id, 'title' => 'Developer']);
        $candidate = Candidate::create(['first_name' => 'John', 'last_name' => 'Doe']);
        $application = CandidateApplication::create([
            'candidate_id' => $candidate->id,
            'posting_id' => $posting->id,
        ]);
        $interview = Interview::create([
            'application_id' => $application->id,
            'interview_date' => now()->addDays(7),
            'start_time' => '10:00',
        ]);

        $evaluation = InterviewEvaluation::create([
            'interview_id' => $interview->id,
            'evaluator_id' => $this->employee->id,
            'overall_score' => 85,
            'strengths' => 'Strong technical skills',
            'is_recommended' => true,
        ]);

        expect($evaluation->isSubmitted())->toBeFalse();

        $evaluation->submit();
        expect($evaluation->isSubmitted())->toBeTrue();
    });
});

describe('CandidateAssessment Model', function (): void {
    test('can create and complete assessment', function (): void {
        $requisition = JobRequisition::create(['title' => 'Developer']);
        $posting = JobPosting::create(['requisition_id' => $requisition->id, 'title' => 'Developer']);
        $candidate = Candidate::create(['first_name' => 'John', 'last_name' => 'Doe']);
        $application = CandidateApplication::create([
            'candidate_id' => $candidate->id,
            'posting_id' => $posting->id,
        ]);

        $assessment = CandidateAssessment::create([
            'application_id' => $application->id,
            'type' => CandidateAssessment::TYPE_TECHNICAL,
            'name' => 'PHP Skills Test',
            'max_score' => 100,
            'passing_score' => 70,
        ]);

        expect($assessment->isPending())->toBeTrue();

        $assessment->start();
        expect($assessment->started_at)->not->toBeNull();

        $assessment->complete(85);
        expect($assessment->isCompleted())->toBeTrue()
            ->and($assessment->is_passed)->toBeTrue()
            ->and($assessment->getScorePercentage())->toBe(85.0);
    });
});

describe('CandidateReference Model', function (): void {
    test('can complete reference check', function (): void {
        $candidate = Candidate::create(['first_name' => 'John', 'last_name' => 'Doe']);

        $reference = CandidateReference::create([
            'candidate_id' => $candidate->id,
            'name' => 'Jane Smith',
            'relationship' => 'supervisor',
            'email' => 'jane@example.com',
        ]);

        expect($reference->isPending())->toBeTrue();

        $reference->contact($this->employee->id);
        expect($reference->status)->toBe('contacted');

        $reference->complete('Excellent employee', 5, true);
        expect($reference->isCompleted())->toBeTrue()
            ->and($reference->is_recommended)->toBeTrue();
    });
});

describe('JobOffer Model', function (): void {
    beforeEach(function (): void {
        $requisition = JobRequisition::create(['title' => 'Developer']);
        $posting = JobPosting::create(['requisition_id' => $requisition->id, 'title' => 'Developer']);
        $candidate = Candidate::create(['first_name' => 'John', 'last_name' => 'Doe']);
        $this->application = CandidateApplication::create([
            'candidate_id' => $candidate->id,
            'posting_id' => $posting->id,
        ]);
    });

    test('can create a job offer', function (): void {
        $offer = JobOffer::create([
            'application_id' => $this->application->id,
            'position_title' => 'Senior Developer',
            'offered_salary' => 75000,
            'start_date' => now()->addMonth(),
            'offer_valid_until' => now()->addWeeks(2),
        ]);

        expect($offer)->toBeInstanceOf(JobOffer::class)
            ->and($offer->status)->toBe(OfferStatus::Draft)
            ->and($offer->isDraft())->toBeTrue();
    });

    test('can send offer', function (): void {
        $offer = JobOffer::create([
            'application_id' => $this->application->id,
            'position_title' => 'Developer',
            'offered_salary' => 60000,
        ]);

        expect($offer->send())->toBeTrue()
            ->and($offer->isSent())->toBeTrue()
            ->and($offer->sent_at)->not->toBeNull();
    });

    test('can accept offer', function (): void {
        $offer = JobOffer::create([
            'application_id' => $this->application->id,
            'position_title' => 'Developer',
            'offered_salary' => 60000,
            'status' => OfferStatus::Sent,
        ]);

        expect($offer->accept('Looking forward to joining'))->toBeTrue()
            ->and($offer->isAccepted())->toBeTrue()
            ->and($offer->candidate_decision)->toBe('accept');
    });

    test('can reject offer', function (): void {
        $offer = JobOffer::create([
            'application_id' => $this->application->id,
            'position_title' => 'Developer',
            'offered_salary' => 60000,
            'status' => OfferStatus::Sent,
        ]);

        expect($offer->reject('Accepted another position'))->toBeTrue()
            ->and($offer->isRejected())->toBeTrue();
    });
});

describe('OfferNegotiation Model', function (): void {
    test('can handle negotiation', function (): void {
        $requisition = JobRequisition::create(['title' => 'Developer']);
        $posting = JobPosting::create(['requisition_id' => $requisition->id, 'title' => 'Developer']);
        $candidate = Candidate::create(['first_name' => 'John', 'last_name' => 'Doe']);
        $application = CandidateApplication::create([
            'candidate_id' => $candidate->id,
            'posting_id' => $posting->id,
        ]);
        $offer = JobOffer::create([
            'application_id' => $application->id,
            'position_title' => 'Developer',
            'offered_salary' => 60000,
        ]);

        $negotiation = OfferNegotiation::create([
            'offer_id' => $offer->id,
            'round' => 1,
            'request' => 'I would like a higher salary',
            'requested_salary' => 70000,
        ]);

        expect($negotiation->isPending())->toBeTrue();

        $negotiation->counterOffer($this->employee->id, 65000, null, 'We can offer 65k');
        expect($negotiation->status)->toBe('counter_offered')
            ->and($negotiation->offered_salary)->toBe('65000.00');
    });
});

describe('OnboardingTask Model', function (): void {
    test('can complete onboarding task', function (): void {
        $task = OnboardingTask::create([
            'employee_id' => $this->employee->id,
            'name' => 'Complete HR paperwork',
            'category' => OnboardingTask::CATEGORY_DOCUMENTATION,
            'due_date' => now()->addDays(7),
        ]);

        expect($task->isPending())->toBeTrue();

        $task->start();
        expect($task->isInProgress())->toBeTrue();

        $task->complete($this->employee->id);
        expect($task->isCompleted())->toBeTrue()
            ->and($task->completed_at)->not->toBeNull();
    });

    test('can identify overdue tasks', function (): void {
        $task = OnboardingTask::create([
            'employee_id' => $this->employee->id,
            'name' => 'Setup workstation',
            'due_date' => now()->subDay(),
        ]);

        expect($task->isOverdue())->toBeTrue();
    });
});
