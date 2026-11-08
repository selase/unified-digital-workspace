<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hrms_attendance_records')) {
            Schema::create('hrms_attendance_records', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->unsignedBigInteger('employee_id')->index();
                $table->date('work_date')->index();
                $table->timestamp('clock_in_at');
                $table->timestamp('clock_out_at')->nullable();
                $table->string('source', 16)->default('web');
                $table->string('note')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'employee_id', 'work_date']);
            });
        }

        if (! Schema::hasTable('hrms_timesheets')) {
            Schema::create('hrms_timesheets', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('tenant_id')->index();
                $table->unsignedBigInteger('employee_id')->index();
                $table->date('week_start');
                $table->string('status', 16)->default('draft');
                $table->unsignedInteger('total_minutes')->default(0);
                $table->timestamp('submitted_at')->nullable();
                $table->uuid('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_comment')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'employee_id', 'week_start']);
            });
        }

        if (! Schema::hasTable('hrms_timesheet_entries')) {
            Schema::create('hrms_timesheet_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('timesheet_id')->constrained('hrms_timesheets')->cascadeOnDelete();
                $table->date('work_date');
                $table->unsignedInteger('minutes');
                $table->string('project')->nullable();
                $table->string('description')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrms_timesheet_entries');
        Schema::dropIfExists('hrms_timesheets');
        Schema::dropIfExists('hrms_attendance_records');
    }
};
