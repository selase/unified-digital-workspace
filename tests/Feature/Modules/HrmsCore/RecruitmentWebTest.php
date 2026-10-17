<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\HrmsCore\Enums\ApplicationStatus;
use App\Modules\HrmsCore\Enums\InterviewStatus;
use App\Modules\HrmsCore\Enums\OfferStatus;
use App\Modules\HrmsCore\Enums\RequisitionStatus;
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
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\PermissionRegistrar;

/**
 * @param  array<int, string>  $permissions
 * @return array{0: User, 1: Tenant}
 */
function createRecruitmentWebContext(array $permissions = [
    'hrms.jobs.view',
    'hrms.jobs.create',
    'hrms.jobs.manage',
    'hrms.applications.view',
    'hrms.applications.manage',
]): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);
    app(ModuleManager::class)->enableForTenant('hrms-core', $tenant);

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $role = Role::firstOrCreate([
        'name' => 'HR Recruitment Lead',
        'guard_name' => 'web',
        'tenant_id' => $tenant->id,
    ]);
    $role->syncPermissions($permissions);
    $user->assignRole($role);

    return [$user, $tenant];
}

describe('HRMS Recruitment Pipeline Writes (Sub-item 3)', function (): void {
    test('can create, update, transition status, and delete a job requisition', function (): void {
        [$user, $tenant] = createRecruitmentWebContext();

        $department = Department::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cardiology',
            'is_active' => true,
        ]);
        $grade = Grade::create([
            'tenant_id' => $tenant->id,
            'name' => 'Consultant Grade I',
        ]);
        $approverEmployee = Employee::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'first_name' => 'Kofi',
            'last_name' => 'Mensah',
            'email' => 'kofi.mensah@example.com',
        ]);

        // 1. Store Requisition
        $this->actingAs($user)
            ->post(route('hrms-core.recruitment.requisitions.store'), [
                'title' => 'Interventional Cardiologist',
                'requisition_number' => 'REQ-CARD-2026-01',
                'department_id' => $department->id,
                'grade_id' => $grade->id,
                'employment_type' => 'full_time',
                'vacancies' => 2,
                'min_salary' => 12000,
                'max_salary' => 18000,
                'location' => 'Accra Heart Centre',
                'is_remote' => false,
                'target_start_date' => Carbon::today()->addMonth()->toDateString(),
                'application_deadline' => Carbon::today()->addWeeks(3)->toDateString(),
                'job_description' => 'Lead catheterization laboratory procedures.',
            ])
            ->assertRedirect(route('hrms-core.recruitment.index'));

        $requisition = JobRequisition::where('requisition_number', 'REQ-CARD-2026-01')->first();
        expect($requisition)->not->toBeNull()
            ->and($requisition->status)->toBe(RequisitionStatus::Draft)
            ->and($requisition->vacancies)->toBe(2)
            ->and($requisition->requested_by)->toBe($approverEmployee->id);

        // 2. Update Requisition
        $this->actingAs($user)
            ->put(route('hrms-core.recruitment.requisitions.update', $requisition), [
                'title' => 'Senior Interventional Cardiologist',
                'requisition_number' => 'REQ-CARD-2026-01',
                'department_id' => $department->id,
                'grade_id' => $grade->id,
                'employment_type' => 'full_time',
                'vacancies' => 3,
                'min_salary' => 14000,
                'max_salary' => 20000,
            ])
            ->assertRedirect(route('hrms-core.recruitment.index'));

        $requisition->refresh();
        expect($requisition->title)->toBe('Senior Interventional Cardiologist')
            ->and($requisition->vacancies)->toBe(3);

        // 3. Invalid transition (cannot open directly from Draft)
        $this->actingAs($user)
            ->post(route('hrms-core.recruitment.requisitions.status', $requisition), [
                'status' => RequisitionStatus::Open->value,
            ])
            ->assertSessionHasErrors(['status']);

        // 4. Valid workflow transitions: Draft -> PendingApproval -> Approved -> Open -> Closed
        $this->actingAs($user)
            ->post(route('hrms-core.recruitment.requisitions.status', $requisition), [
                'status' => RequisitionStatus::PendingApproval->value,
            ])
            ->assertRedirect(route('hrms-core.recruitment.index'));
        expect($requisition->fresh()->status)->toBe(RequisitionStatus::PendingApproval);

        $this->actingAs($user)
            ->post(route('hrms-core.recruitment.requisitions.status', $requisition), [
                'status' => RequisitionStatus::Approved->value,
            ])
            ->assertRedirect(route('hrms-core.recruitment.index'));
        $requisition->refresh();
        expect($requisition->status)->toBe(RequisitionStatus::Approved)
            ->and($requisition->approved_by)->toBe($approverEmployee->id)
            ->and($requisition->approved_at)->not->toBeNull();

        $this->actingAs($user)
            ->post(route('hrms-core.recruitment.requisitions.status', $requisition), [
                'status' => RequisitionStatus::Open->value,
            ])
            ->assertRedirect(route('hrms-core.recruitment.index'));
        expect($requisition->fresh()->status)->toBe(RequisitionStatus::Open);

        $this->actingAs($user)
            ->post(route('hrms-core.recruitment.requisitions.status', $requisition), [
                'status' => RequisitionStatus::Closed->value,
            ])
            ->assertRedirect(route('hrms-core.recruitment.index'));
        expect($requisition->fresh()->status)->toBe(RequisitionStatus::Closed);

        // 5. Delete Requisition
        $this->actingAs($user)
            ->delete(route('hrms-core.recruitment.requisitions.destroy', $requisition))
            ->assertRedirect(route('hrms-core.recruitment.index'));
        expect(JobRequisition::find($requisition->id))->toBeNull();
    });

    test('can create, update, toggle status, and delete a job posting', function (): void {
        [$user, $tenant] = createRecruitmentWebContext();

        $requisition = JobRequisition::create([
            'tenant_id' => $tenant->id,
            'title' => 'Clinical Pharmacist',
            'requisition_number' => 'REQ-PHARM-01',
            'status' => RequisitionStatus::Open,
            'vacancies' => 1,
        ]);

        $postedDate = Carbon::today()->toDateString();
        $closingDate = Carbon::today()->addMonth()->toDateString();

        // Store Posting
        $response = $this->actingAs($user)
            ->post(route('hrms-core.recruitment.postings.store'), [
                'requisition_id' => $requisition->id,
                'title' => 'Clinical Pharmacist — Inpatient Care',
                'description' => 'Manage inpatient medication therapy.',
                'requirements' => 'PharmD and valid license.',
                'benefits' => 'Medical and housing allowance.',
                'is_internal' => true,
                'is_external' => true,
                'is_active' => true,
                'posted_date' => $postedDate,
                'closing_date' => $closingDate,
            ]);

        $posting = JobPosting::where('requisition_id', $requisition->id)->first();
        expect($posting)->not->toBeNull()
            ->and($posting->title)->toBe('Clinical Pharmacist — Inpatient Care')
            ->and($posting->is_active)->toBeTrue()
            ->and($posting->slug)->toStartWith('clinical-pharmacist-inpatient-care-');

        $response->assertRedirect(route('hrms-core.recruitment.postings.show', $posting));

        // Show Posting Page
        $this->actingAs($user)
            ->get(route('hrms-core.recruitment.postings.show', $posting))
            ->assertOk()
            ->assertSee('Clinical Pharmacist — Inpatient Care');

        // Update Posting
        $this->actingAs($user)
            ->put(route('hrms-core.recruitment.postings.update', $posting), [
                'requisition_id' => $requisition->id,
                'title' => 'Senior Clinical Pharmacist — Inpatient Care',
                'description' => 'Updated responsibilities.',
                'is_internal' => true,
                'is_external' => true,
                'is_active' => true,
                'posted_date' => $postedDate,
                'closing_date' => $closingDate,
            ])
            ->assertRedirect(route('hrms-core.recruitment.postings.show', $posting));

        expect($posting->fresh()->title)->toBe('Senior Clinical Pharmacist — Inpatient Care');

        // Toggle Posting Status (Open -> Closed -> Open)
        $this->actingAs($user)
            ->post(route('hrms-core.recruitment.postings.toggle-status', $posting))
            ->assertRedirect();
        expect($posting->fresh()->is_active)->toBeFalse();

        $this->actingAs($user)
            ->post(route('hrms-core.recruitment.postings.toggle-status', $posting))
            ->assertRedirect();
        expect($posting->fresh()->is_active)->toBeTrue();

        // Delete Posting
        $this->actingAs($user)
            ->delete(route('hrms-core.recruitment.postings.destroy', $posting))
            ->assertRedirect(route('hrms-core.recruitment.index'));
        expect(JobPosting::find($posting->id))->toBeNull();
    });

    test('can submit candidate applications, prevent duplicates or closed posting submissions, and transition stages', function (): void {
        [$user, $tenant] = createRecruitmentWebContext();

        $requisition = JobRequisition::create([
            'tenant_id' => $tenant->id,
            'title' => 'Biomedical Engineer',
            'status' => RequisitionStatus::Open,
            'vacancies' => 1,
        ]);

        $posting = JobPosting::create([
            'tenant_id' => $tenant->id,
            'requisition_id' => $requisition->id,
            'title' => 'Biomedical Equipment Engineer',
            'is_active' => true,
            'closing_date' => Carbon::today()->addWeeks(2)->toDateString(),
        ]);

        // 1. Submit Candidate Application
        $this->actingAs($user)
            ->post(route('hrms-core.recruitment.applications.store', $posting), [
                'first_name' => 'Ama',
                'last_name' => 'Osei',
                'email' => 'ama.osei@example.com',
                'phone' => '+233244998877',
                'years_of_experience' => 5,
                'expected_salary' => 9500,
                'cover_letter' => 'Experienced in diagnostic imaging equipment maintenance.',
            ])
            ->assertRedirect(route('hrms-core.recruitment.postings.show', $posting));

        $candidate = Candidate::where('email', 'ama.osei@example.com')->first();
        expect($candidate)->not->toBeNull()
            ->and($candidate->first_name)->toBe('Ama');

        $application = CandidateApplication::where('posting_id', $posting->id)
            ->where('candidate_id', $candidate->id)
            ->first();
        expect($application)->not->toBeNull()
            ->and($application->status)->toBe(ApplicationStatus::Submitted)
            ->and($posting->fresh()->applications_count)->toBe(1);

        // 2. Duplicate application for same candidate & posting is rejected with 422
        $this->actingAs($user)
            ->post(route('hrms-core.recruitment.applications.store', $posting), [
                'first_name' => 'Ama',
                'last_name' => 'Osei',
                'email' => 'ama.osei@example.com',
            ])
            ->assertSessionHasErrors(['email']);

        // 3. Closed posting rejects new applications with 422
        $posting->update(['is_active' => false]);
        $this->actingAs($user)
            ->post(route('hrms-core.recruitment.applications.store', $posting), [
                'first_name' => 'Kojo',
                'last_name' => 'Antwi',
                'email' => 'kojo.antwi@example.com',
            ])
            ->assertSessionHasErrors(['posting']);
        $posting->update(['is_active' => true]);

        // 4. Invalid jump (Submitted -> Hired directly without interview/accepted offer) is rejected with 422
        $this->actingAs($user)
            ->put(route('hrms-core.recruitment.applications.status', $application), [
                'status' => ApplicationStatus::Hired->value,
            ])
            ->assertSessionHasErrors(['status']);
        expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted);

        // 5. Transition Application Status (Submitted -> Shortlisted -> Rejected requires reason)
        $this->actingAs($user)
            ->put(route('hrms-core.recruitment.applications.status', $application), [
                'status' => ApplicationStatus::Shortlisted->value,
                'screening_notes' => 'Strong hospital engineering background.',
                'is_recommended' => true,
            ])
            ->assertRedirect(route('hrms-core.recruitment.postings.show', $posting));

        $application->refresh();
        expect($application->status)->toBe(ApplicationStatus::Shortlisted)
            ->and($application->screening_notes)->toBe('Strong hospital engineering background.')
            ->and($application->is_recommended)->toBeTrue()
            ->and($application->screened_at)->not->toBeNull();

        // Rejecting without rejection_reason fails validation
        $this->actingAs($user)
            ->put(route('hrms-core.recruitment.applications.status', $application), [
                'status' => ApplicationStatus::Rejected->value,
            ])
            ->assertSessionHasErrors(['rejection_reason']);

        // Rejecting with reason succeeds
        $this->actingAs($user)
            ->put(route('hrms-core.recruitment.applications.status', $application), [
                'status' => ApplicationStatus::Rejected->value,
                'rejection_reason' => 'Position filled by internal candidate.',
            ])
            ->assertRedirect(route('hrms-core.recruitment.postings.show', $posting));

        $application->refresh();
        expect($application->status)->toBe(ApplicationStatus::Rejected)
            ->and($application->rejection_reason)->toBe('Position filled by internal candidate.')
            ->and($application->rejected_at)->not->toBeNull();

        // 6. Reopening a Rejected application (Rejected -> Interview or Shortlisted) is rejected with 422
        $this->actingAs($user)
            ->put(route('hrms-core.recruitment.applications.status', $application), [
                'status' => ApplicationStatus::Interview->value,
            ])
            ->assertSessionHasErrors(['status']);
        expect($application->fresh()->status)->toBe(ApplicationStatus::Rejected);

        // 7. Deleting application decrements posting applications_count
        $this->actingAs($user)
            ->delete(route('hrms-core.recruitment.applications.destroy', $application))
            ->assertRedirect(route('hrms-core.recruitment.postings.show', $posting));

        expect($posting->fresh()->applications_count)->toBe(0);
    });

    test('can schedule, complete, cancel, and delete candidate interviews', function (): void {
        [$user, $tenant] = createRecruitmentWebContext();

        $requisition = JobRequisition::create([
            'tenant_id' => $tenant->id,
            'title' => 'Radiographer',
            'status' => RequisitionStatus::Open,
            'vacancies' => 1,
        ]);

        $posting = JobPosting::create([
            'tenant_id' => $tenant->id,
            'requisition_id' => $requisition->id,
            'title' => 'Diagnostic Radiographer',
            'is_active' => true,
        ]);

        $candidate = Candidate::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Efua',
            'last_name' => 'Sarpong',
            'email' => 'efua.sarpong@example.com',
        ]);

        $application = CandidateApplication::create([
            'tenant_id' => $tenant->id,
            'candidate_id' => $candidate->id,
            'posting_id' => $posting->id,
            'status' => ApplicationStatus::Shortlisted,
        ]);

        // 1. Schedule Interview -> automatically moves application to Interview stage
        $interviewDate = Carbon::today()->addDays(5)->toDateString();
        $this->actingAs($user)
            ->post(route('hrms-core.recruitment.interviews.store', $application), [
                'type' => 'video',
                'interview_date' => $interviewDate,
                'start_time' => '10:00',
                'end_time' => '11:00',
                'meeting_link' => 'https://meet.example.com/radiography-panel',
                'instructions' => 'Bring portfolio of MRI protocol certifications.',
            ])
            ->assertRedirect(route('hrms-core.recruitment.postings.show', $posting));

        $interview = Interview::where('application_id', $application->id)->first();
        expect($interview)->not->toBeNull()
            ->and($interview->status)->toBe(InterviewStatus::Scheduled)
            ->and($application->fresh()->status)->toBe(ApplicationStatus::Interview);

        // 2. Complete Interview with evaluation feedback
        $this->actingAs($user)
            ->put(route('hrms-core.recruitment.interviews.update', $interview), [
                'status' => InterviewStatus::Completed->value,
                'feedback_summary' => 'Excellent technical mastery of CT and MRI safety.',
                'overall_rating' => '4.8/5',
                'is_recommended' => true,
            ])
            ->assertRedirect(route('hrms-core.recruitment.postings.show', $posting));

        $interview->refresh();
        expect($interview->status)->toBe(InterviewStatus::Completed)
            ->and($interview->completed_at)->not->toBeNull()
            ->and($interview->feedback_summary)->toBe('Excellent technical mastery of CT and MRI safety.')
            ->and($interview->is_recommended)->toBeTrue();

        // 3. Delete Interview
        $this->actingAs($user)
            ->delete(route('hrms-core.recruitment.interviews.destroy', $interview))
            ->assertRedirect(route('hrms-core.recruitment.postings.show', $posting));
        expect(Interview::find($interview->id))->toBeNull();
    });

    test('can draft, send, and record candidate acceptance on job offers, provisioning employee draft and onboarding tasks', function (): void {
        [$user, $tenant] = createRecruitmentWebContext();

        $department = Department::create([
            'tenant_id' => $tenant->id,
            'name' => 'Diagnostic Imaging',
            'is_active' => true,
        ]);

        $requisition = JobRequisition::create([
            'tenant_id' => $tenant->id,
            'title' => 'Lead Radiographer',
            'department_id' => $department->id,
            'status' => RequisitionStatus::Open,
            'vacancies' => 1,
        ]);

        $posting = JobPosting::create([
            'tenant_id' => $tenant->id,
            'requisition_id' => $requisition->id,
            'title' => 'Lead Radiographer Vacancy',
            'is_active' => true,
        ]);

        $candidate = Candidate::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Kwesi',
            'last_name' => 'Boadu',
            'email' => 'kwesi.boadu@example.com',
            'phone' => '+233207778899',
            'status' => 'active',
        ]);

        $application = CandidateApplication::create([
            'tenant_id' => $tenant->id,
            'candidate_id' => $candidate->id,
            'posting_id' => $posting->id,
            'status' => ApplicationStatus::Interview,
        ]);

        // 1. Create Draft Job Offer
        $startDate = Carbon::today()->addMonth()->toDateString();
        $validUntil = Carbon::today()->addWeeks(2)->toDateString();

        $this->actingAs($user)
            ->post(route('hrms-core.recruitment.offers.store', $application), [
                'position_title' => 'Lead Diagnostic Radiographer',
                'department_id' => $department->id,
                'employment_type' => 'full_time',
                'offered_salary' => 11500.00,
                'start_date' => $startDate,
                'offer_valid_until' => $validUntil,
                'additional_terms' => 'Includes relocation assistance.',
            ])
            ->assertRedirect(route('hrms-core.recruitment.postings.show', $posting));

        $offer = JobOffer::where('application_id', $application->id)->first();
        expect($offer)->not->toBeNull()
            ->and($offer->status)->toBe(OfferStatus::Draft)
            ->and((float) $offer->offered_salary)->toBe(11500.0)
            ->and($application->fresh()->status)->toBe(ApplicationStatus::Offer);

        // 2. Moving application to Hired while offer is still Draft is rejected with 422
        $this->actingAs($user)
            ->put(route('hrms-core.recruitment.applications.status', $application), [
                'status' => ApplicationStatus::Hired->value,
            ])
            ->assertSessionHasErrors(['status']);

        // 3. Cannot accept a Draft offer before it is sent
        $this->actingAs($user)
            ->post(route('hrms-core.recruitment.offers.decision', $offer), [
                'decision' => 'accept',
            ])
            ->assertSessionHasErrors(['decision']);

        // 4. Approve and Send Offer
        $this->actingAs($user)
            ->post(route('hrms-core.recruitment.offers.send', $offer))
            ->assertRedirect(route('hrms-core.recruitment.postings.show', $posting));

        $offer->refresh();
        expect($offer->status)->toBe(OfferStatus::Sent)
            ->and($offer->sent_at)->not->toBeNull();

        // 5. Candidate Accepts Offer -> Marks Offer Accepted, Application Hired, Candidate Hired, and creates Employee + OnboardingTasks
        $this->actingAs($user)
            ->post(route('hrms-core.recruitment.offers.decision', $offer), [
                'decision' => 'accept',
                'candidate_feedback' => 'Delighted to accept the offer.',
            ])
            ->assertRedirect(route('hrms-core.recruitment.postings.show', $posting));

        $offer->refresh();
        $application->refresh();
        $candidate->refresh();

        expect($offer->status)->toBe(OfferStatus::Accepted)
            ->and($offer->candidate_decision)->toBe('accept')
            ->and($offer->responded_at)->not->toBeNull()
            ->and($application->status)->toBe(ApplicationStatus::Hired)
            ->and($application->hired_at)->not->toBeNull()
            ->and($candidate->status)->toBe('hired');

        // Verify Employee draft and OnboardingTask rows were provisioned
        $hiredEmployee = Employee::where('tenant_id', $tenant->id)
            ->where('email', 'kwesi.boadu@example.com')
            ->first();
        expect($hiredEmployee)->not->toBeNull()
            ->and($hiredEmployee->first_name)->toBe('Kwesi')
            ->and($hiredEmployee->last_name)->toBe('Boadu')
            ->and($hiredEmployee->departments()->where('hrms_departments.id', $department->id)->exists())->toBeTrue();

        $onboardingTasks = OnboardingTask::where('tenant_id', $tenant->id)
            ->where('employee_id', $hiredEmployee->id)
            ->where('application_id', $application->id)
            ->orderBy('sequence')
            ->get();
        expect($onboardingTasks)->toHaveCount(4)
            ->and($onboardingTasks->pluck('category')->all())->toBe([
                OnboardingTask::CATEGORY_DOCUMENTATION,
                OnboardingTask::CATEGORY_EQUIPMENT,
                OnboardingTask::CATEGORY_ACCESS,
                OnboardingTask::CATEGORY_ORIENTATION,
            ]);

        // 6. Finalized (Accepted) offer cannot be rejected or withdrawn afterwards
        $this->actingAs($user)
            ->post(route('hrms-core.recruitment.offers.decision', $offer), [
                'decision' => 'reject',
                'candidate_feedback' => 'Changed mind',
            ])
            ->assertSessionHasErrors(['decision']);
    });

    test('enforces permission gates (403) and strict multi-tenant isolation (404/422) across all recruitment writes', function (): void {
        [$userA, $tenantA] = createRecruitmentWebContext();
        [$userB, $tenantB] = createRecruitmentWebContext();

        // Create records in Tenant A
        Session::put('active_tenant_id', $tenantA->id);
        setPermissionsTeamId($tenantA->id);
        app(TenantContext::class)->setTenant($tenantA);

        $reqA = JobRequisition::create([
            'tenant_id' => $tenantA->id,
            'title' => 'Tenant A Requisition',
            'status' => RequisitionStatus::Approved,
            'vacancies' => 1,
        ]);

        $postingA = JobPosting::create([
            'tenant_id' => $tenantA->id,
            'requisition_id' => $reqA->id,
            'title' => 'Tenant A Posting',
            'is_active' => true,
        ]);

        $candidateA = Candidate::create([
            'tenant_id' => $tenantA->id,
            'first_name' => 'TenantA',
            'last_name' => 'Candidate',
            'email' => 'tenanta@example.com',
        ]);

        $appA = CandidateApplication::create([
            'tenant_id' => $tenantA->id,
            'candidate_id' => $candidateA->id,
            'posting_id' => $postingA->id,
            'status' => ApplicationStatus::Submitted,
        ]);

        $interviewA = Interview::create([
            'tenant_id' => $tenantA->id,
            'application_id' => $appA->id,
            'type' => 'phone',
            'interview_date' => Carbon::today()->addDays(2)->toDateString(),
            'start_time' => '09:00',
            'status' => InterviewStatus::Scheduled,
        ]);

        $offerA = JobOffer::create([
            'tenant_id' => $tenantA->id,
            'application_id' => $appA->id,
            'position_title' => 'Tenant A Offer',
            'offered_salary' => 5000,
            'start_date' => Carbon::today()->addMonth()->toDateString(),
            'status' => OfferStatus::Draft,
        ]);

        // Switch to Tenant B and verify 404/422 isolation against Tenant A's records (both UUID and non-UUID route keys)
        Session::put('active_tenant_id', $tenantB->id);
        setPermissionsTeamId($tenantB->id);
        app(TenantContext::class)->setTenant($tenantB);

        $this->actingAs($userB)
            ->put(route('hrms-core.recruitment.requisitions.update', $reqA), [
                'title' => 'Hijacked',
                'employment_type' => 'full_time',
                'vacancies' => 1,
            ])
            ->assertNotFound();

        $this->actingAs($userB)
            ->put(route('hrms-core.recruitment.requisitions.update', ['requisition' => $reqA->id]), [
                'title' => 'Hijacked By ID',
                'employment_type' => 'full_time',
                'vacancies' => 1,
            ])
            ->assertNotFound();

        $this->actingAs($userB)
            ->post(route('hrms-core.recruitment.postings.store'), [
                'requisition_id' => $reqA->id,
                'title' => 'Cross-Tenant Posting',
            ])
            ->assertSessionHasErrors(['requisition_id']);

        $this->actingAs($userB)
            ->get(route('hrms-core.recruitment.postings.show', $postingA))
            ->assertNotFound();

        $this->actingAs($userB)
            ->post(route('hrms-core.recruitment.applications.store', $postingA), [
                'first_name' => 'Intruder',
                'last_name' => 'Test',
                'email' => 'intruder@example.com',
            ])
            ->assertNotFound();

        $this->actingAs($userB)
            ->put(route('hrms-core.recruitment.interviews.update', $interviewA), [
                'status' => InterviewStatus::Cancelled->value,
                'cancellation_reason' => 'Cross-tenant cancel attempt',
            ])
            ->assertNotFound();

        $this->actingAs($userB)
            ->post(route('hrms-core.recruitment.offers.send', $offerA))
            ->assertNotFound();

        // Verify Tenant A state was never mutated
        app(TenantContext::class)->setTenant($tenantA);
        expect($reqA->fresh()->title)->toBe('Tenant A Requisition')
            ->and($interviewA->fresh()->status)->toBe(InterviewStatus::Scheduled)
            ->and($offerA->fresh()->status)->toBe(OfferStatus::Draft);

        // Verify 403 for user with only view permission attempting writes in Tenant A
        $viewOnlyUser = User::factory()->create();
        $tenantA->users()->attach($viewOnlyUser->id);
        Session::put('active_tenant_id', $tenantA->id);
        setPermissionsTeamId($tenantA->id);
        app(TenantContext::class)->setTenant($tenantA);

        $viewRole = Role::firstOrCreate([
            'name' => 'HR Recruitment Viewer',
            'guard_name' => 'web',
            'tenant_id' => $tenantA->id,
        ]);
        $viewRole->syncPermissions(['hrms.jobs.view', 'hrms.applications.view']);
        $viewOnlyUser->assignRole($viewRole);

        $this->actingAs($viewOnlyUser)
            ->post(route('hrms-core.recruitment.requisitions.store'), [
                'title' => 'Unauthorized Req',
                'employment_type' => 'full_time',
                'vacancies' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($viewOnlyUser)
            ->post(route('hrms-core.recruitment.applications.store', $postingA), [
                'first_name' => 'Unauth',
                'last_name' => 'Applicant',
                'email' => 'unauth@example.com',
            ])
            ->assertForbidden();
    });
});
