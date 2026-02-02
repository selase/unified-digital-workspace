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
        // Salary Levels - Grade-linked salary levels
        Schema::create('hrms_salary_levels', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('grade_id')->nullable()->constrained('hrms_grades')->nullOnDelete();
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'is_active']);
        });

        // Salary Steps - Step increments within each grade level
        Schema::create('hrms_salary_steps', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('grade_id')->nullable()->constrained('hrms_grades')->nullOnDelete();
            $table->foreignId('salary_level_id')->nullable()->constrained('hrms_salary_levels')->nullOnDelete();
            $table->decimal('step_increment', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->integer('step_number')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'grade_id']);
        });

        // Allowance Types - Categories of allowances
        Schema::create('hrms_allowance_types', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'is_active']);
        });

        // Allowances - Specific allowance definitions with amounts
        Schema::create('hrms_allowances', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('allowance_type_id')->constrained('hrms_allowance_types')->cascadeOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained('hrms_grades')->nullOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('frequency')->default('monthly'); // monthly, annual, one-time
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'allowance_type_id']);
        });

        // Employee Allowances - Allowances assigned to employees
        Schema::create('hrms_employee_allowances', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->foreignId('allowance_id')->constrained('hrms_allowances')->cascadeOnDelete();
            $table->decimal('amount', 15, 2)->nullable(); // Override default if set
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'employee_id']);
            $table->index(['effective_from', 'effective_to']);
        });

        // Add salary-related columns to employees table
        Schema::table('hrms_employees', function (Blueprint $table): void {
            $table->foreignId('salary_level_id')->nullable()->after('grade_id')->constrained('hrms_salary_levels')->nullOnDelete();
            $table->foreignId('salary_step_id')->nullable()->after('salary_level_id')->constrained('hrms_salary_steps')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hrms_employees', function (Blueprint $table): void {
            $table->dropForeign(['salary_level_id']);
            $table->dropForeign(['salary_step_id']);
            $table->dropColumn(['salary_level_id', 'salary_step_id']);
        });

        Schema::dropIfExists('hrms_employee_allowances');
        Schema::dropIfExists('hrms_allowances');
        Schema::dropIfExists('hrms_allowance_types');
        Schema::dropIfExists('hrms_salary_steps');
        Schema::dropIfExists('hrms_salary_levels');
    }
};
