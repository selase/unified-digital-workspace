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
        // Employee - Department pivot
        Schema::create('hrms_department_employee', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('hrms_departments')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'department_id']);
        });

        // Employee - DepartmentType pivot
        Schema::create('hrms_department_type_employee', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->foreignId('department_type_id')->constrained('hrms_department_types')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'department_type_id']);
        });

        // Employee - Directorate pivot
        Schema::create('hrms_directorate_employee', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->foreignId('directorate_id')->constrained('hrms_directorates')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'directorate_id']);
        });

        // Employee - Unit pivot
        Schema::create('hrms_employee_unit', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('hrms_units')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'unit_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hrms_employee_unit');
        Schema::dropIfExists('hrms_directorate_employee');
        Schema::dropIfExists('hrms_department_type_employee');
        Schema::dropIfExists('hrms_department_employee');
    }
};
