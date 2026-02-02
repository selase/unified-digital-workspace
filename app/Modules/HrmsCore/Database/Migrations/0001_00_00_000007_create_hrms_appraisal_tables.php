<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Appraisal Periods - Define review cycles (Annual 2024, Mid-year Q2, etc.)
        Schema::create('hrms_appraisal_periods', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('cycle'); // AppraisalCycle enum: annual, midyear, quarterly, probation
            $table->date('start_date');
            $table->date('end_date');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'is_active']);
        });

        // Appraisal Templates - Reusable templates with name/description
        Schema::create('hrms_appraisal_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->foreignId('period_id')->nullable()->constrained('hrms_appraisal_periods')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'is_active']);
        });

        // Appraisal Sections - Sections within templates (Performance, Competencies, Goals)
        Schema::create('hrms_appraisal_sections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->foreignId('template_id')->constrained('hrms_appraisal_templates')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('weight', 5, 2)->default(0); // Percentage weight for scoring
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['template_id', 'sort_order']);
        });

        // Appraisal Criteria - Individual criteria to rate within sections
        Schema::create('hrms_appraisal_criteria', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->foreignId('section_id')->constrained('hrms_appraisal_sections')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('weight', 5, 2)->default(0); // Percentage weight within section
            $table->boolean('is_required')->default(true);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['section_id', 'sort_order']);
        });

        // Appraisal Rating Scales - Rating definitions (1-5 scale with labels)
        Schema::create('hrms_appraisal_rating_scales', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->foreignId('period_id')->nullable()->constrained('hrms_appraisal_periods')->cascadeOnDelete();
            $table->integer('value'); // 1, 2, 3, 4, 5
            $table->string('label'); // Unsatisfactory, Below Expectations, etc.
            $table->text('description')->nullable();
            $table->string('color')->nullable(); // CSS color for UI
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'period_id', 'value']);
        });

        // Appraisals - Main appraisal record linking employee to period/template
        Schema::create('hrms_appraisals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('hrms_appraisal_periods')->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('hrms_appraisal_templates')->cascadeOnDelete();

            // Status workflow
            $table->string('status')->default('draft'); // AppraisalStatus enum

            // Workflow actors
            $table->foreignId('supervisor_id')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->foreignId('hod_id')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->foreignId('hr_reviewer_id')->nullable()->constrained('hrms_employees')->nullOnDelete();

            // Workflow timestamps
            $table->timestamp('self_assessment_submitted_at')->nullable();
            $table->timestamp('supervisor_reviewed_at')->nullable();
            $table->timestamp('hod_reviewed_at')->nullable();
            $table->timestamp('hr_reviewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Scores
            $table->decimal('self_overall_score', 5, 2)->nullable();
            $table->decimal('supervisor_overall_score', 5, 2)->nullable();
            $table->decimal('final_overall_score', 5, 2)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['employee_id', 'period_id']); // One appraisal per employee per period
            $table->index(['tenant_id', 'status']);
            $table->index(['period_id', 'status']);
        });

        // Appraisal Responses - Employee responses to each criterion
        Schema::create('hrms_appraisal_responses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->foreignId('appraisal_id')->constrained('hrms_appraisals')->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained('hrms_appraisal_criteria')->cascadeOnDelete();

            // Self-assessment
            $table->integer('self_rating')->nullable();
            $table->text('self_comments')->nullable();

            // Supervisor assessment
            $table->integer('supervisor_rating')->nullable();
            $table->text('supervisor_comments')->nullable();

            // Final (HOD/HR adjusted)
            $table->integer('final_rating')->nullable();
            $table->text('final_comments')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['appraisal_id', 'criterion_id']);
        });

        // Appraisal Goals - Goals set for the appraisal period
        Schema::create('hrms_appraisal_goals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->foreignId('appraisal_id')->constrained('hrms_appraisals')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('key_results')->nullable(); // Expected outcomes
            $table->text('target')->nullable(); // Target metrics
            $table->text('achievement')->nullable(); // Actual achievement
            $table->string('status')->default('not_started'); // GoalStatus enum
            $table->decimal('weight', 5, 2)->default(0); // Percentage weight
            $table->integer('self_rating')->nullable();
            $table->integer('supervisor_rating')->nullable();
            $table->text('employee_comments')->nullable();
            $table->text('supervisor_comments')->nullable();
            $table->date('due_date')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['appraisal_id', 'status']);
        });

        // Appraisal Competencies - Competency assessments
        Schema::create('hrms_appraisal_competencies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->foreignId('appraisal_id')->constrained('hrms_appraisals')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('behavioral_indicators')->nullable(); // Expected behaviors
            $table->integer('self_rating')->nullable();
            $table->text('self_evidence')->nullable(); // Examples/evidence
            $table->integer('supervisor_rating')->nullable();
            $table->text('supervisor_evidence')->nullable();
            $table->integer('final_rating')->nullable();
            $table->decimal('weight', 5, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['appraisal_id']);
        });

        // Appraisal Reviews - Supervisor/HOD/HR review records
        Schema::create('hrms_appraisal_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->foreignId('appraisal_id')->constrained('hrms_appraisals')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->string('reviewer_type'); // supervisor, hod, hr
            $table->decimal('overall_rating', 5, 2)->nullable();
            $table->text('strengths')->nullable();
            $table->text('areas_for_improvement')->nullable();
            $table->text('general_comments')->nullable();
            $table->string('decision')->nullable(); // approved, revision_requested, etc.
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['appraisal_id', 'reviewer_type']);
        });

        // Appraisal Comments - Comments from any reviewer
        Schema::create('hrms_appraisal_comments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->foreignId('appraisal_id')->constrained('hrms_appraisals')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->text('comment');
            $table->string('type')->default('general'); // general, feedback, action_item
            $table->boolean('is_private')->default(false); // Only visible to HR/management
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['appraisal_id', 'created_at']);
        });

        // Appraisal Scores - Calculated scores per section/overall
        Schema::create('hrms_appraisal_scores', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->foreignId('appraisal_id')->constrained('hrms_appraisals')->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('hrms_appraisal_sections')->cascadeOnDelete();
            $table->decimal('self_score', 5, 2)->nullable();
            $table->decimal('supervisor_score', 5, 2)->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->decimal('weighted_score', 5, 2)->nullable(); // Score * weight
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['appraisal_id', 'section_id']);
        });

        // Appraisal Recommendations - Final recommendations (promotion, training, etc.)
        Schema::create('hrms_appraisal_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->foreignId('appraisal_id')->constrained('hrms_appraisals')->cascadeOnDelete();
            $table->string('type'); // RecommendationType enum: promotion, training, recognition, pip, termination
            $table->text('description')->nullable();
            $table->text('action_plan')->nullable();
            $table->date('target_date')->nullable();
            $table->foreignId('recommended_by')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected, implemented
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['appraisal_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hrms_appraisal_recommendations');
        Schema::dropIfExists('hrms_appraisal_scores');
        Schema::dropIfExists('hrms_appraisal_comments');
        Schema::dropIfExists('hrms_appraisal_reviews');
        Schema::dropIfExists('hrms_appraisal_competencies');
        Schema::dropIfExists('hrms_appraisal_goals');
        Schema::dropIfExists('hrms_appraisal_responses');
        Schema::dropIfExists('hrms_appraisals');
        Schema::dropIfExists('hrms_appraisal_rating_scales');
        Schema::dropIfExists('hrms_appraisal_criteria');
        Schema::dropIfExists('hrms_appraisal_sections');
        Schema::dropIfExists('hrms_appraisal_templates');
        Schema::dropIfExists('hrms_appraisal_periods');
    }
};
