<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database connection that should be used by the migration.
     */
    protected $connection = 'landlord';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Job requisitions - requests to fill positions
        Schema::connection($this->connection)->create('hrms_job_requisitions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id');

            $table->string('title');
            $table->string('requisition_number')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('hrms_departments')->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained('hrms_grades')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('hrms_employees')->nullOnDelete();

            $table->text('job_description')->nullable();
            $table->text('requirements')->nullable();
            $table->text('responsibilities')->nullable();
            $table->string('employment_type')->default('full_time'); // full_time, part_time, contract, temporary
            $table->integer('vacancies')->default(1);
            $table->decimal('min_salary', 12, 2)->nullable();
            $table->decimal('max_salary', 12, 2)->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_remote')->default(false);

            $table->string('status')->default('draft'); // draft, pending_approval, approved, open, closed, cancelled
            $table->date('target_start_date')->nullable();
            $table->date('application_deadline')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('status');
        });

        // Job postings - published job listings
        Schema::connection($this->connection)->create('hrms_job_postings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id');

            $table->foreignId('requisition_id')->constrained('hrms_job_requisitions')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->text('benefits')->nullable();
            $table->string('slug')->nullable();

            $table->boolean('is_internal')->default(false);
            $table->boolean('is_external')->default(true);
            $table->boolean('is_active')->default(true);

            $table->date('posted_date')->nullable();
            $table->date('closing_date')->nullable();
            $table->integer('views_count')->default(0);
            $table->integer('applications_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['is_active', 'closing_date']);
        });

        // Candidates - job applicants
        Schema::connection($this->connection)->create('hrms_candidates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id');

            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('alternate_phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();

            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->nullable();
            $table->string('marital_status')->nullable();

            $table->string('current_employer')->nullable();
            $table->string('current_position')->nullable();
            $table->decimal('current_salary', 12, 2)->nullable();
            $table->decimal('expected_salary', 12, 2)->nullable();
            $table->string('notice_period')->nullable();
            $table->integer('years_of_experience')->nullable();

            $table->string('highest_qualification')->nullable();
            $table->string('institution')->nullable();
            $table->string('graduation_year')->nullable();

            $table->text('skills')->nullable(); // JSON array of skills
            $table->text('languages')->nullable(); // JSON array of languages

            $table->string('source')->nullable(); // job_portal, referral, direct, agency
            $table->string('referrer_name')->nullable();
            $table->string('referrer_email')->nullable();

            $table->string('status')->default('active'); // active, blacklisted, hired
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('email');
            $table->index('status');
        });

        // Candidate applications - applications to specific jobs
        Schema::connection($this->connection)->create('hrms_candidate_applications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id');

            $table->foreignId('candidate_id')->constrained('hrms_candidates')->cascadeOnDelete();
            $table->foreignId('posting_id')->constrained('hrms_job_postings')->cascadeOnDelete();
            $table->string('application_number')->nullable();

            $table->string('status')->default('submitted'); // submitted, screening, shortlisted, interview, assessment, offer, hired, rejected, withdrawn
            $table->string('stage')->default('application'); // application, hr_screening, technical, final, offer

            $table->text('cover_letter')->nullable();
            $table->decimal('offered_salary', 12, 2)->nullable();
            $table->date('proposed_start_date')->nullable();

            $table->foreignId('screened_by')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->timestamp('screened_at')->nullable();
            $table->boolean('is_recommended')->nullable();
            $table->text('screening_notes')->nullable();

            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('hrms_employees')->nullOnDelete();

            $table->timestamp('hired_at')->nullable();
            $table->foreignId('hired_by')->nullable()->constrained('hrms_employees')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('status');
            $table->unique(['candidate_id', 'posting_id']);
        });

        // Candidate documents - resumes, cover letters, certificates
        Schema::connection($this->connection)->create('hrms_candidate_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id');

            $table->foreignId('candidate_id')->constrained('hrms_candidates')->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('hrms_candidate_applications')->cascadeOnDelete();

            $table->string('type'); // resume, cover_letter, certificate, id_card, other
            $table->string('name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->integer('file_size')->nullable();

            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['candidate_id', 'type']);
        });

        // Interview stages - workflow stages
        Schema::connection($this->connection)->create('hrms_interview_stages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id');

            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('sequence')->default(0);
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('tenant_id');
        });

        // Interviews - scheduled interviews
        Schema::connection($this->connection)->create('hrms_interviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id');

            $table->foreignId('application_id')->constrained('hrms_candidate_applications')->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('hrms_interview_stages')->nullOnDelete();

            $table->string('type')->default('in_person'); // in_person, phone, video
            $table->date('interview_date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->string('location')->nullable();
            $table->string('meeting_link')->nullable();

            $table->string('status')->default('scheduled'); // scheduled, confirmed, completed, cancelled, no_show
            $table->text('instructions')->nullable();
            $table->text('feedback_summary')->nullable();
            $table->string('overall_rating')->nullable();
            $table->boolean('is_recommended')->nullable();

            $table->foreignId('scheduled_by')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['application_id', 'status']);
            $table->index('interview_date');
        });

        // Interview panels - interviewers for each interview
        Schema::connection($this->connection)->create('hrms_interview_panels', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id');

            $table->foreignId('interview_id')->constrained('hrms_interviews')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();

            $table->string('role')->default('interviewer'); // lead, interviewer, observer
            $table->boolean('is_mandatory')->default(true);
            $table->string('attendance_status')->default('pending'); // pending, confirmed, attended, absent
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['interview_id', 'employee_id']);
        });

        // Interview evaluations - panel member evaluations
        Schema::connection($this->connection)->create('hrms_interview_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id');

            $table->foreignId('interview_id')->constrained('hrms_interviews')->cascadeOnDelete();
            $table->foreignId('evaluator_id')->constrained('hrms_employees')->cascadeOnDelete();

            $table->text('criteria_scores')->nullable(); // JSON object with criteria and scores
            $table->integer('overall_score')->nullable();
            $table->string('overall_rating')->nullable();
            $table->text('strengths')->nullable();
            $table->text('weaknesses')->nullable();
            $table->text('comments')->nullable();
            $table->boolean('is_recommended')->nullable();
            $table->text('recommendation_notes')->nullable();

            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['interview_id', 'evaluator_id']);
        });

        // Candidate assessments - tests and evaluations
        Schema::connection($this->connection)->create('hrms_candidate_assessments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id');

            $table->foreignId('application_id')->constrained('hrms_candidate_applications')->cascadeOnDelete();
            $table->string('type'); // technical, aptitude, personality, skills, language
            $table->string('name');
            $table->text('description')->nullable();

            $table->string('status')->default('pending'); // pending, in_progress, completed, expired
            $table->decimal('score', 5, 2)->nullable();
            $table->decimal('max_score', 5, 2)->nullable();
            $table->decimal('passing_score', 5, 2)->nullable();
            $table->boolean('is_passed')->nullable();
            $table->text('results')->nullable(); // JSON detailed results
            $table->text('feedback')->nullable();

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->foreignId('evaluated_by')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->timestamp('evaluated_at')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['application_id', 'status']);
        });

        // Candidate references - reference checks
        Schema::connection($this->connection)->create('hrms_candidate_references', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id');

            $table->foreignId('candidate_id')->constrained('hrms_candidates')->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('hrms_candidate_applications')->cascadeOnDelete();

            $table->string('name');
            $table->string('relationship'); // supervisor, colleague, manager, professor, etc.
            $table->string('company')->nullable();
            $table->string('position')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->string('status')->default('pending'); // pending, contacted, completed, no_response
            $table->text('feedback')->nullable();
            $table->integer('rating')->nullable();
            $table->boolean('is_recommended')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('checked_by')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
        });

        // Job offers - offers made to candidates
        Schema::connection($this->connection)->create('hrms_job_offers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id');

            $table->foreignId('application_id')->constrained('hrms_candidate_applications')->cascadeOnDelete();
            $table->string('offer_number')->nullable();

            $table->string('position_title');
            $table->foreignId('department_id')->nullable()->constrained('hrms_departments')->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained('hrms_grades')->nullOnDelete();
            $table->foreignId('salary_level_id')->nullable()->constrained('hrms_salary_levels')->nullOnDelete();

            $table->string('employment_type')->default('full_time');
            $table->decimal('offered_salary', 12, 2)->nullable();
            $table->text('benefits')->nullable(); // JSON object
            $table->text('additional_terms')->nullable();

            $table->date('start_date')->nullable();
            $table->date('offer_valid_until')->nullable();

            $table->string('status')->default('draft'); // draft, pending_approval, sent, accepted, rejected, withdrawn, expired
            $table->foreignId('approved_by')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->string('candidate_decision')->nullable(); // accept, reject, negotiate
            $table->text('candidate_feedback')->nullable();
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('status');
        });

        // Offer negotiations - negotiation tracking
        Schema::connection($this->connection)->create('hrms_offer_negotiations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id');

            $table->foreignId('offer_id')->constrained('hrms_job_offers')->cascadeOnDelete();
            $table->integer('round')->default(1);

            $table->string('initiated_by')->default('candidate'); // candidate, employer
            $table->text('request')->nullable();
            $table->text('response')->nullable();
            $table->string('status')->default('pending'); // pending, accepted, rejected, counter_offered

            $table->decimal('requested_salary', 12, 2)->nullable();
            $table->decimal('offered_salary', 12, 2)->nullable();
            $table->text('requested_benefits')->nullable(); // JSON
            $table->text('offered_benefits')->nullable(); // JSON

            $table->foreignId('handled_by')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['offer_id', 'round']);
        });

        // Onboarding tasks - tasks for new hires
        Schema::connection($this->connection)->create('hrms_onboarding_tasks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id');

            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('hrms_candidate_applications')->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable(); // documentation, training, equipment, access, orientation
            $table->integer('sequence')->default(0);

            $table->string('status')->default('pending'); // pending, in_progress, completed, skipped
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->foreignId('assigned_to')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['employee_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('hrms_onboarding_tasks');
        Schema::connection($this->connection)->dropIfExists('hrms_offer_negotiations');
        Schema::connection($this->connection)->dropIfExists('hrms_job_offers');
        Schema::connection($this->connection)->dropIfExists('hrms_candidate_references');
        Schema::connection($this->connection)->dropIfExists('hrms_candidate_assessments');
        Schema::connection($this->connection)->dropIfExists('hrms_interview_evaluations');
        Schema::connection($this->connection)->dropIfExists('hrms_interview_panels');
        Schema::connection($this->connection)->dropIfExists('hrms_interviews');
        Schema::connection($this->connection)->dropIfExists('hrms_interview_stages');
        Schema::connection($this->connection)->dropIfExists('hrms_candidate_documents');
        Schema::connection($this->connection)->dropIfExists('hrms_candidate_applications');
        Schema::connection($this->connection)->dropIfExists('hrms_candidates');
        Schema::connection($this->connection)->dropIfExists('hrms_job_postings');
        Schema::connection($this->connection)->dropIfExists('hrms_job_requisitions');
    }
};
