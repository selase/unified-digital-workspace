<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hrms_current_jobs', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->index();
        });

        Schema::table('hrms_employee_parents', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->index();
        });

        Schema::table('hrms_children', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->index();
        });

        Schema::table('hrms_next_of_kin', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->index();
        });

        Schema::table('hrms_bank_details', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->index();
        });

        Schema::table('hrms_emergency_contacts', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->index();
        });

        Schema::table('hrms_educational_backgrounds', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->index();
        });

        Schema::table('hrms_professional_qualifications', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->index();
        });

        Schema::table('hrms_previous_work_experiences', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->index();
        });

        Schema::table('hrms_employment_statuses', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->index();
        });

        DB::statement('UPDATE hrms_current_jobs SET tenant_id = (SELECT tenant_id FROM hrms_employees WHERE hrms_employees.id = hrms_current_jobs.employee_id) WHERE tenant_id IS NULL');
        DB::statement('UPDATE hrms_employee_parents SET tenant_id = (SELECT tenant_id FROM hrms_employees WHERE hrms_employees.id = hrms_employee_parents.employee_id) WHERE tenant_id IS NULL');
        DB::statement('UPDATE hrms_children SET tenant_id = (SELECT tenant_id FROM hrms_employees WHERE hrms_employees.id = hrms_children.employee_id) WHERE tenant_id IS NULL');
        DB::statement('UPDATE hrms_next_of_kin SET tenant_id = (SELECT tenant_id FROM hrms_employees WHERE hrms_employees.id = hrms_next_of_kin.employee_id) WHERE tenant_id IS NULL');
        DB::statement('UPDATE hrms_bank_details SET tenant_id = (SELECT tenant_id FROM hrms_employees WHERE hrms_employees.id = hrms_bank_details.employee_id) WHERE tenant_id IS NULL');
        DB::statement('UPDATE hrms_emergency_contacts SET tenant_id = (SELECT tenant_id FROM hrms_employees WHERE hrms_employees.id = hrms_emergency_contacts.employee_id) WHERE tenant_id IS NULL');
        DB::statement('UPDATE hrms_educational_backgrounds SET tenant_id = (SELECT tenant_id FROM hrms_employees WHERE hrms_employees.id = hrms_educational_backgrounds.employee_id) WHERE tenant_id IS NULL');
        DB::statement('UPDATE hrms_professional_qualifications SET tenant_id = (SELECT tenant_id FROM hrms_employees WHERE hrms_employees.id = hrms_professional_qualifications.employee_id) WHERE tenant_id IS NULL');
        DB::statement('UPDATE hrms_previous_work_experiences SET tenant_id = (SELECT tenant_id FROM hrms_employees WHERE hrms_employees.id = hrms_previous_work_experiences.employee_id) WHERE tenant_id IS NULL');
        DB::statement('UPDATE hrms_employment_statuses SET tenant_id = (SELECT tenant_id FROM hrms_employees WHERE hrms_employees.id = hrms_employment_statuses.employee_id) WHERE tenant_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hrms_current_jobs', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });

        Schema::table('hrms_employee_parents', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });

        Schema::table('hrms_children', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });

        Schema::table('hrms_next_of_kin', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });

        Schema::table('hrms_bank_details', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });

        Schema::table('hrms_emergency_contacts', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });

        Schema::table('hrms_educational_backgrounds', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });

        Schema::table('hrms_professional_qualifications', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });

        Schema::table('hrms_previous_work_experiences', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });

        Schema::table('hrms_employment_statuses', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });
    }
};
