<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hrms_directorates', function (Blueprint $table): void {
            if (! Schema::hasColumn('hrms_directorates', 'head_employee_id')) {
                $table->foreignId('head_employee_id')->nullable()->constrained('hrms_employees')->nullOnDelete();
            }
            if (! Schema::hasColumn('hrms_directorates', 'deputy_employee_id')) {
                $table->foreignId('deputy_employee_id')->nullable()->constrained('hrms_employees')->nullOnDelete();
            }
        });

        Schema::table('hrms_departments', function (Blueprint $table): void {
            if (! Schema::hasColumn('hrms_departments', 'directorate_id')) {
                $table->foreignId('directorate_id')->nullable()->constrained('hrms_directorates')->nullOnDelete();
            }
            if (! Schema::hasColumn('hrms_departments', 'head_employee_id')) {
                $table->foreignId('head_employee_id')->nullable()->constrained('hrms_employees')->nullOnDelete();
            }
            if (! Schema::hasColumn('hrms_departments', 'deputy_employee_id')) {
                $table->foreignId('deputy_employee_id')->nullable()->constrained('hrms_employees')->nullOnDelete();
            }
        });

        Schema::table('hrms_units', function (Blueprint $table): void {
            if (! Schema::hasColumn('hrms_units', 'department_id')) {
                $table->foreignId('department_id')->nullable()->constrained('hrms_departments')->nullOnDelete();
            }
            if (! Schema::hasColumn('hrms_units', 'directorate_id')) {
                $table->foreignId('directorate_id')->nullable()->constrained('hrms_directorates')->nullOnDelete();
            }
            if (! Schema::hasColumn('hrms_units', 'head_employee_id')) {
                $table->foreignId('head_employee_id')->nullable()->constrained('hrms_employees')->nullOnDelete();
            }
            if (! Schema::hasColumn('hrms_units', 'deputy_employee_id')) {
                $table->foreignId('deputy_employee_id')->nullable()->constrained('hrms_employees')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('hrms_org_role_assignments')) {
            Schema::create('hrms_org_role_assignments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->string('assignable_type');
                $table->unsignedBigInteger('assignable_id');
                $table->unsignedBigInteger('role_id')->nullable();
                $table->string('role_name');
                $table->uuid('assigned_by_id')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'assignable_type', 'assignable_id', 'role_name'], 'hrms_org_roles_unique');
                $table->index(['tenant_id', 'assignable_type', 'assignable_id'], 'hrms_org_roles_lookup');
            });
        }

        if (! Schema::hasTable('hrms_org_permission_assignments')) {
            Schema::create('hrms_org_permission_assignments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->string('assignable_type');
                $table->unsignedBigInteger('assignable_id');
                $table->string('permission_name');
                $table->uuid('assigned_by_id')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'assignable_type', 'assignable_id', 'permission_name'], 'hrms_org_perms_unique');
                $table->index(['tenant_id', 'assignable_type', 'assignable_id'], 'hrms_org_perms_lookup');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrms_org_permission_assignments');
        Schema::dropIfExists('hrms_org_role_assignments');

        Schema::table('hrms_units', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('deputy_employee_id');
            $table->dropConstrainedForeignId('head_employee_id');
            $table->dropConstrainedForeignId('directorate_id');
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::table('hrms_departments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('deputy_employee_id');
            $table->dropConstrainedForeignId('head_employee_id');
            $table->dropConstrainedForeignId('directorate_id');
        });

        Schema::table('hrms_directorates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('deputy_employee_id');
            $table->dropConstrainedForeignId('head_employee_id');
        });
    }
};
