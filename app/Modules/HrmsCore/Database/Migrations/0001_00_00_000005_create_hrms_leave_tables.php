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
        // Leave Categories - Types of leave with default days
        Schema::create('hrms_leave_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->integer('default_days');
            $table->text('description')->nullable();
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_documentation')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'is_active']);
        });

        // Holidays - Public holidays that don't count against leave
        Schema::create('hrms_holidays', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->date('date');
            $table->text('description')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'date']);
            $table->unique(['tenant_id', 'date', 'name']);
        });

        // Leave Balances - Track employee leave balances by category and year
        Schema::create('hrms_leave_balances', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->foreignId('leave_category_id')->constrained('hrms_leave_categories')->cascadeOnDelete();
            $table->year('year');
            $table->integer('entitled_days')->default(0);
            $table->integer('carried_forward_days')->default(0);
            $table->integer('used_days')->default(0);
            $table->integer('pending_days')->default(0);
            $table->integer('remaining_days')->storedAs('entitled_days + carried_forward_days - used_days - pending_days');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['employee_id', 'leave_category_id', 'year']);
            $table->index(['tenant_id', 'year']);
        });

        // Leave Requests - Unified leave request table
        Schema::create('hrms_leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->foreignId('leave_category_id')->constrained('hrms_leave_categories')->cascadeOnDelete();

            // Request details
            $table->date('proposed_start_date');
            $table->date('proposed_end_date');
            $table->integer('no_requested_days');
            $table->text('leave_reasons')->nullable();
            $table->string('contact_when_away')->nullable();

            // Workflow actors (nullable because assigned during workflow)
            $table->foreignId('supervisor_id')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->foreignId('hod_id')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->foreignId('hr_verifier_id')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->foreignId('relieving_officer_id')->nullable()->constrained('hrms_employees')->nullOnDelete();

            // Supervisor recommendation
            $table->integer('no_recommended_days')->nullable();
            $table->date('recommended_start_date')->nullable();
            $table->date('recommended_end_date')->nullable();
            $table->text('supervisor_comments')->nullable();
            $table->timestamp('supervisor_verified_at')->nullable();

            // HR verification
            $table->text('hr_comments')->nullable();
            $table->timestamp('hr_verified_at')->nullable();

            // Status tracking (uses LeaveStatus enum)
            $table->string('status')->default('pending');
            $table->string('hr_verification_status')->nullable();

            // HOD decision
            $table->integer('no_of_days_approved')->nullable();
            $table->date('approved_start_date')->nullable();
            $table->date('approved_end_date')->nullable();
            $table->text('hod_comments')->nullable();
            $table->timestamp('hod_decision_at')->nullable();

            // Date calculations
            $table->date('resumption_date')->nullable();
            $table->integer('no_of_holidays_in_period')->default(0);
            $table->integer('no_of_weekends_in_period')->default(0);

            // Recall functionality
            $table->boolean('is_recalled')->default(false);
            $table->date('recall_date')->nullable();
            $table->integer('no_of_days_recalled')->nullable();
            $table->text('recall_reason')->nullable();
            $table->timestamp('recalled_at')->nullable();

            // Balance snapshot at time of request
            $table->integer('balance_at_request')->nullable();
            $table->integer('carry_forward_at_request')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
            $table->index(['employee_id', 'status']);
            $table->index(['proposed_start_date', 'proposed_end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hrms_leave_requests');
        Schema::dropIfExists('hrms_leave_balances');
        Schema::dropIfExists('hrms_holidays');
        Schema::dropIfExists('hrms_leave_categories');
    }
};
