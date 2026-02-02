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
        // Current Job assignment
        Schema::create('hrms_current_jobs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->string('job_title');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->index('employee_id');
        });

        // Employee parents information
        Schema::create('hrms_employee_parents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->string('father_name')->nullable();
            $table->boolean('father_alive')->default(true);
            $table->string('father_occupation')->nullable();
            $table->string('mother_name')->nullable();
            $table->boolean('mother_alive')->default(true);
            $table->string('mother_occupation')->nullable();
            $table->timestamps();

            $table->index('employee_id');
        });

        // Employee children
        Schema::create('hrms_children', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->string('name');
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->timestamps();

            $table->index('employee_id');
        });

        // Next of kin
        Schema::create('hrms_next_of_kin', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();

            $table->index('employee_id');
        });

        // Bank details
        Schema::create('hrms_bank_details', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('branch_name')->nullable();
            $table->string('account_number');
            $table->string('account_type')->nullable();
            $table->string('sort_code')->nullable();
            $table->timestamps();

            $table->index('employee_id');
        });

        // Emergency contacts
        Schema::create('hrms_emergency_contacts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship')->nullable();
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('employee_id');
        });

        // Educational background
        Schema::create('hrms_educational_backgrounds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->string('institution_name');
            $table->string('qualification');
            $table->string('field_of_study')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('grade')->nullable();
            $table->timestamps();

            $table->index('employee_id');
        });

        // Professional qualifications
        Schema::create('hrms_professional_qualifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->string('certification_name');
            $table->string('issuing_body');
            $table->date('date_obtained')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('certificate_number')->nullable();
            $table->timestamps();

            $table->index('employee_id');
        });

        // Previous work experience
        Schema::create('hrms_previous_work_experiences', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->string('company_name');
            $table->string('position');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('responsibilities')->nullable();
            $table->string('reason_for_leaving')->nullable();
            $table->timestamps();

            $table->index('employee_id');
        });

        // Employment status history
        Schema::create('hrms_employment_statuses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->string('status'); // active, on_leave, suspended, terminated, resigned, retired
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->text('reason')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->index(['employee_id', 'is_current']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hrms_employment_statuses');
        Schema::dropIfExists('hrms_previous_work_experiences');
        Schema::dropIfExists('hrms_professional_qualifications');
        Schema::dropIfExists('hrms_educational_backgrounds');
        Schema::dropIfExists('hrms_emergency_contacts');
        Schema::dropIfExists('hrms_bank_details');
        Schema::dropIfExists('hrms_next_of_kin');
        Schema::dropIfExists('hrms_children');
        Schema::dropIfExists('hrms_employee_parents');
        Schema::dropIfExists('hrms_current_jobs');
    }
};
