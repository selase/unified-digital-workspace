<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\CmsCore\Models\Post;
use App\Modules\DocumentManagement\Models\Document;
use App\Modules\Forums\Models\ForumChannel;
use App\Modules\Forums\Models\ForumMessage;
use App\Modules\Forums\Models\ForumMessageRecipient;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Organization\Directorate;
use App\Modules\HrmsCore\Models\Organization\OrgPermissionAssignment;
use App\Modules\HrmsCore\Models\Organization\Unit;
use App\Modules\IncidentManagement\Models\Incident;
use App\Modules\Memos\Models\Memo;
use App\Modules\Memos\Models\MemoRecipient;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\QualityMonitoring\Models\Workplan;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Database\Seeders\InstitutionalMasterSeeder;
use Illuminate\Support\Facades\Artisan;

describe('Phase 17: InstitutionalMasterSeeder Showcase Data Verification', function (): void {
    test('seeds rich 3-tier hierarchy, Org RBAC grants, To/CC/BCC correspondence, notifications, and all module datasets', function (): void {
        $admin = User::factory()->create([
            'email' => 'dev@wearepurpledot.com',
            'first_name' => 'Purpledot',
            'last_name' => 'Developer',
        ]);
        $tenant = setActiveTenantForTest($admin, [
            'slug' => 'purpledot',
            'name' => 'Purpledot',
            'isolation_mode' => 'shared',
        ]);

        app(TenantContext::class)->setTenant($tenant);
        app(TenantDatabaseManager::class)->configure($tenant);

        $moduleMigrationPaths = [
            'Modules/HrmsCore/Database/Migrations',
            'Modules/Memos/Database/Migrations',
            'Modules/DocumentManagement/Database/Migrations',
            'Modules/IncidentManagement/Database/Migrations',
            'Modules/QualityMonitoring/Database/Migrations',
            'Modules/ProjectManagement/Database/Migrations',
            'Modules/Forums/Database/Migrations',
            'Modules/CmsCore/Database/Migrations',
            'Modules/Crm/Database/Migrations',
            'Modules/VendorMarketplace/Database/Migrations',
            'Modules/RealEstate/Database/Migrations',
            'Modules/FacilitiesManagement/Database/Migrations',
            'Modules/Intranet/Database/Migrations',
            'Modules/Forms/Database/Migrations',
            'Modules/Store/Database/Migrations',
            'Modules/Reports/Database/Migrations',
            'Modules/WorkflowAutomation/Database/Migrations',
        ];

        foreach ($moduleMigrationPaths as $path) {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => app_path($path),
                '--realpath' => true,
                '--force' => true,
            ]);
        }

        $this->seed(InstitutionalMasterSeeder::class);

        expect(Directorate::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(3)
            ->and(Department::query()->where('tenant_id', $tenant->id)->whereNotNull('directorate_id')->count())->toBeGreaterThanOrEqual(3)
            ->and(Unit::query()->where('tenant_id', $tenant->id)->whereNotNull('department_id')->count())->toBeGreaterThanOrEqual(3)
            ->and(Employee::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(6)
            ->and(OrgPermissionAssignment::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(7)
            ->and(Memo::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(2)
            ->and(MemoRecipient::query()->where('tenant_id', $tenant->id)->where('role', MemoRecipient::ROLE_BCC)->count())->toBeGreaterThanOrEqual(1)
            ->and(ForumMessage::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(ForumMessageRecipient::query()->where('tenant_id', $tenant->id)->where('role', ForumMessageRecipient::ROLE_BCC)->count())->toBeGreaterThanOrEqual(1)
            ->and(Document::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(Incident::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(Workplan::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(Project::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(ForumChannel::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(Post::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(App\Modules\Crm\Models\CrmLead::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(App\Modules\VendorMarketplace\Models\VendorProfile::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(App\Modules\RealEstate\Models\PropertyListing::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(App\Modules\FacilitiesManagement\Models\FacilityAsset::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(App\Modules\Intranet\Models\IntranetEvent::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(App\Modules\Forms\Models\FormDefinition::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(2)
            ->and(App\Modules\Store\Models\StoreProduct::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(App\Modules\Reports\Models\ReportDefinition::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(App\Modules\WorkflowAutomation\Models\WorkflowRule::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(App\Modules\Crm\Models\CrmLead::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(App\Modules\VendorMarketplace\Models\VendorProfile::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(App\Modules\RealEstate\Models\PropertyListing::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(App\Modules\FacilitiesManagement\Models\FacilityAsset::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(App\Modules\Intranet\Models\IntranetEvent::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(App\Modules\Forms\Models\FormDefinition::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(2)
            ->and(App\Modules\Store\Models\StoreProduct::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(App\Modules\Reports\Models\ReportDefinition::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(App\Modules\WorkflowAutomation\Models\WorkflowRule::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
            ->and($admin->unreadNotifications()->count())->toBeGreaterThanOrEqual(2);
    });
});
