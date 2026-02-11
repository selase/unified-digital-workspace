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

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->create('hrms_staff_promotions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id');

            // Employee being promoted
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();

            // Promotion details
            $table->string('category')->default('promotion'); // PromotionCategory enum
            $table->string('status')->default('pending'); // PromotionStatus enum

            // Grade changes
            $table->foreignId('from_grade_id')->nullable()->constrained('hrms_grades')->nullOnDelete();
            $table->foreignId('to_grade_id')->nullable()->constrained('hrms_grades')->nullOnDelete();

            // Salary level changes (optional)
            $table->foreignId('from_salary_level_id')->nullable()->constrained('hrms_salary_levels')->nullOnDelete();
            $table->foreignId('to_salary_level_id')->nullable()->constrained('hrms_salary_levels')->nullOnDelete();

            // Dates
            $table->date('effective_date')->nullable();
            $table->date('requested_date')->nullable();

            // Justification
            $table->text('reason')->nullable();
            $table->text('justification')->nullable();
            $table->text('supporting_documents')->nullable(); // JSON array of document paths

            // Supervisor approval
            $table->foreignId('supervisor_id')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->boolean('supervisor_approved')->nullable();
            $table->text('supervisor_comments')->nullable();
            $table->timestamp('supervisor_reviewed_at')->nullable();

            // HR approval
            $table->foreignId('hr_approver_id')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->boolean('hr_approved')->nullable();
            $table->text('hr_comments')->nullable();
            $table->timestamp('hr_reviewed_at')->nullable();

            // Final decision
            $table->text('rejection_reason')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('tenant_id');
            $table->index('status');
            $table->index(['employee_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('hrms_staff_promotions');
    }
};
