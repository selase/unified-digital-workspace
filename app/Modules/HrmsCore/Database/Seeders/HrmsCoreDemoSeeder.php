<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Database\Seeders;

use App\Models\User;
use App\Modules\HrmsCore\Enums\LeaveStatus;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Leave\LeaveCategory;
use App\Modules\HrmsCore\Models\Leave\LeaveRequest;
use App\Modules\HrmsCore\Models\Organization\Center;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Organization\DepartmentType;
use App\Modules\HrmsCore\Models\Organization\Grade;
use App\Modules\HrmsCore\Models\Recruitment\JobPosting;
use App\Modules\HrmsCore\Models\Recruitment\JobRequisition;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

final class HrmsCoreDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->bound(TenantContext::class)) {
            return;
        }

        $tenant = app(TenantContext::class)->getTenant();

        if (! $tenant) {
            return;
        }

        $tenantConnection = config('database.default_tenant_connection', 'tenant');

        if (! Schema::connection($tenantConnection)->hasTable('hrms_employees')) {
            return;
        }

        $tenantUser = User::query()
            ->where('tenant_id', $tenant->id)
            ->first() ?? User::query()->first();

        if (! $tenantUser) {
            return;
        }

        $grade = Grade::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'grade-7',
        ], [
            'name' => 'Grade 7',
            'description' => 'Supervisory grade level.',
            'can_recommend_leave' => true,
            'can_approve_leave' => true,
            'can_appraise' => true,
            'sort_order' => 7,
        ]);

        $center = Center::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'accra-hq',
        ], [
            'name' => 'Accra HQ',
            'location' => 'Airport Residential Area, Accra',
            'description' => 'Main operating center for the tenant.',
            'is_active' => true,
        ]);

        $department = Department::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'operations',
        ], [
            'name' => 'Operations',
            'description' => 'Service operations and delivery management.',
            'is_active' => true,
        ]);

        $departmentType = DepartmentType::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'service-delivery',
        ], [
            'department_id' => $department->id,
            'name' => 'Service Delivery',
            'description' => 'Service quality and execution unit.',
            'is_active' => true,
        ]);

        $employee = Employee::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'email' => $tenantUser->email,
        ], [
            'user_id' => $tenantUser->id,
            'employee_staff_id' => 'EMP-10001',
            'title' => 'Mr.',
            'first_name' => $tenantUser->first_name,
            'last_name' => $tenantUser->last_name,
            'gender' => 'male',
            'nationality' => 'Ghanaian',
            'marital_status' => 'single',
            'mobile' => (string) ($tenantUser->phone_no ?? '+233000000000'),
            'residential_address' => 'Accra, Ghana',
            'town' => 'Accra',
            'region' => 'Greater Accra',
            'is_any_disability' => false,
            'is_any_children' => false,
            'grade_id' => $grade->id,
            'center_id' => $center->id,
            'is_active' => true,
        ]);

        $employee->departments()->syncWithoutDetaching([$department->id]);
        $employee->departmentTypes()->syncWithoutDetaching([$departmentType->id]);

        $leaveCategory = LeaveCategory::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'annual-leave',
        ], [
            'name' => 'Annual Leave',
            'default_days' => 21,
            'description' => 'Annual rest leave entitlement.',
            'is_paid' => true,
            'requires_documentation' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        LeaveRequest::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'proposed_start_date' => now()->addWeeks(2)->toDateString(),
        ], [
            'leave_category_id' => $leaveCategory->id,
            'proposed_end_date' => now()->addWeeks(2)->addDays(4)->toDateString(),
            'no_requested_days' => 5,
            'leave_reasons' => 'Family leave planned in advance.',
            'contact_when_away' => '+233000000000',
            'status' => LeaveStatus::Pending,
            'no_of_holidays_in_period' => 0,
            'no_of_weekends_in_period' => 2,
            'is_recalled' => false,
        ]);

        $requisition = JobRequisition::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'requisition_number' => 'REQ-2026-0001',
        ], [
            'title' => 'Quality Monitoring Officer',
            'department_id' => $department->id,
            'grade_id' => $grade->id,
            'requested_by' => $employee->id,
            'approved_by' => $employee->id,
            'job_description' => 'Own quality monitoring dashboards and variance tracking.',
            'requirements' => '3+ years in monitoring and evaluation roles.',
            'responsibilities' => 'Prepare KPI updates and weekly quality reviews.',
            'employment_type' => 'full_time',
            'vacancies' => 1,
            'min_salary' => 4500,
            'max_salary' => 6500,
            'location' => 'Accra',
            'is_remote' => false,
            'status' => 'open',
            'target_start_date' => now()->addMonth()->toDateString(),
            'application_deadline' => now()->addWeeks(3)->toDateString(),
            'approved_at' => now()->subDays(3),
        ]);

        JobPosting::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'requisition_id' => $requisition->id,
            'slug' => 'quality-monitoring-officer',
        ], [
            'title' => 'Quality Monitoring Officer',
            'description' => 'Support workplan reporting and monthly performance reviews.',
            'requirements' => 'Data analysis, KPI reporting, and governance documentation.',
            'benefits' => 'Medical cover, annual bonus, and professional development support.',
            'is_internal' => false,
            'is_external' => true,
            'is_active' => true,
            'posted_date' => now()->subDays(2)->toDateString(),
            'closing_date' => now()->addWeeks(3)->toDateString(),
            'views_count' => 42,
            'applications_count' => 6,
        ]);
    }
}
