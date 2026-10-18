<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\CmsCore\Database\Seeders\CmsCoreSeeder;
use App\Modules\DocumentManagement\Database\Seeders\DocumentManagementSeeder;
use App\Modules\Forums\Database\Seeders\ForumsSeeder;
use App\Modules\Forums\Models\ForumMessage;
use App\Modules\Forums\Models\ForumMessageRecipient;
use App\Modules\HrmsCore\Database\Seeders\HrmsCoreSeeder;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Organization\Center;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Organization\Directorate;
use App\Modules\HrmsCore\Models\Organization\Grade;
use App\Modules\HrmsCore\Models\Organization\OrgPermissionAssignment;
use App\Modules\HrmsCore\Models\Organization\Unit;
use App\Modules\IncidentManagement\Database\Seeders\IncidentManagementSeeder;
use App\Modules\Memos\Database\Seeders\MemosSeeder;
use App\Modules\Memos\Models\Memo;
use App\Modules\Memos\Models\MemoRecipient;
use App\Modules\ProjectManagement\Database\Seeders\ProjectManagementSeeder;
use App\Modules\QualityMonitoring\Database\Seeders\QualityMonitoringSeeder;
use App\Notifications\DirectMessageReceivedNotification;
use App\Notifications\MemoDispatchedNotification;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

final class InstitutionalMasterSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = app()->bound(TenantContext::class)
            ? app(TenantContext::class)->getTenant()
            : null;

        if (! $tenant instanceof Tenant) {
            $tenant = Tenant::query()->where('slug', 'purpledot')->first()
                ?? Tenant::query()->where('isolation_mode', 'shared')->first();
        }

        if (! $tenant instanceof Tenant) {
            return;
        }

        app(TenantContext::class)->setTenant($tenant);
        app(TenantDatabaseManager::class)->configure($tenant);

        $moduleManager = app(ModuleManager::class);
        $coreModules = [
            'hrms-core',
            'memos',
            'document-management',
            'incident-management',
            'quality-monitoring',
            'project-management',
            'forums',
            'cms-core',
            'crm',
            'vendor-marketplace',
            'real-estate',
            'facilities-management',
            'intranet',
            'forms',
            'store',
            'reports',
            'workflow-automation',
        ];

        foreach ($coreModules as $slug) {
            if ($moduleManager->exists($slug)) {
                $moduleManager->enableForTenant($slug, $tenant);
            }
        }

        // Run baseline module seeders first
        $this->call([
            HrmsCoreSeeder::class,
            MemosSeeder::class,
            DocumentManagementSeeder::class,
            IncidentManagementSeeder::class,
            QualityMonitoringSeeder::class,
            ProjectManagementSeeder::class,
            ForumsSeeder::class,
            CmsCoreSeeder::class,
            \App\Modules\FacilitiesManagement\Database\Seeders\FacilitiesManagementSeeder::class,
        ]);

        $this->seedHierarchyAndRbac($tenant);
        $this->seedMultiRecipientMessagingAndNotifications($tenant);
        $this->seedInstitutionalExpansionModules($tenant);
    }

    private function seedHierarchyAndRbac(Tenant $tenant): void
    {
        $tenantConnection = config('database.default_tenant_connection', 'tenant');

        if (! Schema::connection($tenantConnection)->hasTable('hrms_directorates')) {
            return;
        }

        $grade = Grade::query()->where('tenant_id', $tenant->id)->first();
        $center = Center::query()->where('tenant_id', $tenant->id)->first();

        $staffProfiles = [
            [
                'email' => 'kwame.mensah@wearepurpledot.com',
                'first_name' => 'Kwame',
                'last_name' => 'Mensah',
                'title' => 'Dr.',
                'staff_id' => 'EMP-20001',
                'gender' => 'male',
            ],
            [
                'email' => 'abena.osei@wearepurpledot.com',
                'first_name' => 'Abena',
                'last_name' => 'Osei',
                'title' => 'Dr.',
                'staff_id' => 'EMP-20002',
                'gender' => 'female',
            ],
            [
                'email' => 'kofi.boateng@wearepurpledot.com',
                'first_name' => 'Kofi',
                'last_name' => 'Boateng',
                'title' => 'Ing.',
                'staff_id' => 'EMP-20003',
                'gender' => 'male',
            ],
            [
                'email' => 'ama.darko@wearepurpledot.com',
                'first_name' => 'Ama',
                'last_name' => 'Darko',
                'title' => 'Ms.',
                'staff_id' => 'EMP-20004',
                'gender' => 'female',
            ],
            [
                'email' => 'nana.owusu@wearepurpledot.com',
                'first_name' => 'Nana Yaw',
                'last_name' => 'Owusu',
                'title' => 'Mr.',
                'staff_id' => 'EMP-20005',
                'gender' => 'male',
            ],
            [
                'email' => 'efua.asante@wearepurpledot.com',
                'first_name' => 'Efua',
                'last_name' => 'Asante',
                'title' => 'Mrs.',
                'staff_id' => 'EMP-20006',
                'gender' => 'female',
            ],
        ];

        /** @var array<string, Employee> $employees */
        $employees = [];

        foreach ($staffProfiles as $profile) {
            $user = User::query()->updateOrCreate([
                'email' => $profile['email'],
            ], [
                'first_name' => $profile['first_name'],
                'last_name' => $profile['last_name'],
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
                'phone_no' => '233208000100',
                'status' => User::STATUS_ACTIVE,
                'tenant_id' => $tenant->id,
            ]);

            $user->tenants()->syncWithoutDetaching([$tenant->id]);

            $employees[$profile['staff_id']] = Employee::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'email' => $profile['email'],
            ], [
                'user_id' => $user->id,
                'employee_staff_id' => $profile['staff_id'],
                'title' => $profile['title'],
                'first_name' => $profile['first_name'],
                'last_name' => $profile['last_name'],
                'gender' => $profile['gender'],
                'nationality' => 'Ghanaian',
                'marital_status' => 'married',
                'mobile' => '+233208000100',
                'residential_address' => 'East Legon, Accra',
                'town' => 'Accra',
                'region' => 'Greater Accra',
                'grade_id' => $grade?->id,
                'center_id' => $center?->id,
                'is_active' => true,
            ]);
        }

        // 1. Directorates with Heads and Deputies
        $clinicalDir = Directorate::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'clinical-services-directorate',
        ], [
            'name' => 'Clinical Services Directorate',
            'description' => 'Executive oversight of clinical care, medical governance, and emergency response.',
            'head_employee_id' => $employees['EMP-20001']->id,
            'deputy_employee_id' => $employees['EMP-20002']->id,
            'is_active' => true,
        ]);

        $digitalDir = Directorate::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'corporate-digital-operations-directorate',
        ], [
            'name' => 'Corporate & Digital Operations Directorate',
            'description' => 'Digital transformation, enterprise engineering, and institutional quality standards.',
            'head_employee_id' => $employees['EMP-20003']->id,
            'deputy_employee_id' => $employees['EMP-20004']->id,
            'is_active' => true,
        ]);

        $estatesDir = Directorate::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'finance-procurement-estates-directorate',
        ], [
            'name' => 'Finance, Procurement & Estates Directorate',
            'description' => 'Real estate holdings, facility management, vendor procurement, and treasury.',
            'head_employee_id' => $employees['EMP-20005']->id,
            'deputy_employee_id' => $employees['EMP-20006']->id,
            'is_active' => true,
        ]);

        // 2. Departments linked to Directorates
        $emergencyDept = Department::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'emergency-critical-care',
        ], [
            'name' => 'Emergency & Critical Care',
            'description' => '24/7 acute care, trauma response, and intensive care units.',
            'directorate_id' => $clinicalDir->id,
            'head_employee_id' => $employees['EMP-20002']->id,
            'deputy_employee_id' => $employees['EMP-20001']->id,
            'is_active' => true,
        ]);

        $systemsDept = Department::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'enterprise-systems-cybersecurity',
        ], [
            'name' => 'Enterprise Systems & Cybersecurity',
            'description' => 'Core platform infrastructure, data security, and internal automation.',
            'directorate_id' => $digitalDir->id,
            'head_employee_id' => $employees['EMP-20004']->id,
            'deputy_employee_id' => $employees['EMP-20003']->id,
            'is_active' => true,
        ]);

        $capitalDept = Department::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'capital-projects-estates',
        ], [
            'name' => 'Capital Projects & Estates',
            'description' => 'Land acquisition, building development, and institutional asset lifecycle.',
            'directorate_id' => $estatesDir->id,
            'head_employee_id' => $employees['EMP-20006']->id,
            'deputy_employee_id' => $employees['EMP-20005']->id,
            'is_active' => true,
        ]);

        // 3. Units linked to Departments & Directorates
        $traumaUnit = Unit::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'trauma-triage-unit',
        ], [
            'name' => 'Trauma & Triage Unit',
            'description' => 'Rapid assessment and clinical incident stabilization unit.',
            'directorate_id' => $clinicalDir->id,
            'department_id' => $emergencyDept->id,
            'head_employee_id' => $employees['EMP-20001']->id,
            'deputy_employee_id' => $employees['EMP-20002']->id,
            'is_active' => true,
        ]);

        $cloudUnit = Unit::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'cloud-infrastructure-devsecops-unit',
        ], [
            'name' => 'Cloud Infrastructure & DevSecOps Unit',
            'description' => 'Zero-trust network operations and multi-tenant reliability.',
            'directorate_id' => $digitalDir->id,
            'department_id' => $systemsDept->id,
            'head_employee_id' => $employees['EMP-20003']->id,
            'deputy_employee_id' => $employees['EMP-20004']->id,
            'is_active' => true,
        ]);

        $facilitiesUnit = Unit::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'land-facility-maintenance-unit',
        ], [
            'name' => 'Land & Facility Maintenance Unit',
            'description' => 'Preventive maintenance, artisan dispatch, and land boundary verification.',
            'directorate_id' => $estatesDir->id,
            'department_id' => $capitalDept->id,
            'head_employee_id' => $employees['EMP-20005']->id,
            'deputy_employee_id' => $employees['EMP-20006']->id,
            'is_active' => true,
        ]);

        // Sync employees (heads & deputies) to hierarchy
        $employees['EMP-20001']->directorates()->syncWithoutDetaching([$clinicalDir->id]);
        $employees['EMP-20001']->departments()->syncWithoutDetaching([$emergencyDept->id]);
        $employees['EMP-20001']->units()->syncWithoutDetaching([$traumaUnit->id]);

        $employees['EMP-20002']->directorates()->syncWithoutDetaching([$clinicalDir->id]);
        $employees['EMP-20002']->departments()->syncWithoutDetaching([$emergencyDept->id]);
        $employees['EMP-20002']->units()->syncWithoutDetaching([$traumaUnit->id]);

        $employees['EMP-20003']->directorates()->syncWithoutDetaching([$digitalDir->id]);
        $employees['EMP-20003']->departments()->syncWithoutDetaching([$systemsDept->id]);
        $employees['EMP-20003']->units()->syncWithoutDetaching([$cloudUnit->id]);

        $employees['EMP-20004']->directorates()->syncWithoutDetaching([$digitalDir->id]);
        $employees['EMP-20004']->departments()->syncWithoutDetaching([$systemsDept->id]);
        $employees['EMP-20004']->units()->syncWithoutDetaching([$cloudUnit->id]);

        $employees['EMP-20005']->directorates()->syncWithoutDetaching([$estatesDir->id]);
        $employees['EMP-20005']->departments()->syncWithoutDetaching([$capitalDept->id]);
        $employees['EMP-20005']->units()->syncWithoutDetaching([$facilitiesUnit->id]);

        $employees['EMP-20006']->directorates()->syncWithoutDetaching([$estatesDir->id]);
        $employees['EMP-20006']->departments()->syncWithoutDetaching([$capitalDept->id]);
        $employees['EMP-20006']->units()->syncWithoutDetaching([$facilitiesUnit->id]);

        // 4. Centralized Organizational RBAC Grants
        if (Schema::connection($tenantConnection)->hasTable('hrms_org_permission_assignments')) {
            // Universal baseline staff permissions (read + active operations)
            $universalStaffPermissions = [
                'hrms.employees.view',
                'memos.view',
                'memos.create',
                'memos.update',
                'memos.send',
                'memos.sign',
                'memos.acknowledge',
                'memos.minute',
                'memos.share',
                'documents.view',
                'incidents.view',
                'incidents.create',
                'forums.view',
                'forums.post',
                'forums.messages.send',
                'forms.view',
                'forms.submit',
                'intranet.view',
                'intranet.boards.post',
            ];

            $directoratesMap = [
                $digitalDir->id => array_merge($universalStaffPermissions, [
                    'projects.view',
                    'projects.create',
                    'projects.tasks.manage',
                    'quality.view',
                ]),
                $estatesDir->id => array_merge($universalStaffPermissions, [
                    'facilities.view',
                    'facilities.occupant.request',
                    'facilities.assets.manage',
                    'facilities.maintenance.manage',
                    'realestate.view',
                    'crm.view',
                    'vendors.view',
                ]),
                $clinicalDir->id => array_merge($universalStaffPermissions, [
                    'quality.view',
                    'incidents.tasks.manage',
                ]),
            ];

            foreach ($directoratesMap as $directorateId => $perms) {
                foreach ($perms as $permName) {
                    Permission::firstOrCreate([
                        'name' => $permName,
                        'guard_name' => 'web',
                    ], [
                        'category' => Str::before($permName, '.'),
                        'uuid' => (string) Str::uuid(),
                    ]);

                    OrgPermissionAssignment::query()->updateOrCreate([
                        'tenant_id' => $tenant->id,
                        'assignable_type' => Directorate::class,
                        'assignable_id' => $directorateId,
                        'permission_name' => $permName,
                    ]);
                }
            }

            \App\Services\Auth\OrgPermissionResolver::flushForTenant($tenant->id);
        }
    }

    private function seedMultiRecipientMessagingAndNotifications(Tenant $tenant): void
    {
        $tenantConnection = config('database.default_tenant_connection', 'tenant');

        if (! Schema::connection($tenantConnection)->hasTable('memos')) {
            return;
        }

        $primaryUser = User::query()->where('email', 'dev@wearepurpledot.com')->first()
            ?? User::query()->where('tenant_id', $tenant->id)->first();

        $ccUser = User::query()->where('email', 'kwame.mensah@wearepurpledot.com')->first();
        $bccUser = User::query()->where('email', 'nana.owusu@wearepurpledot.com')->first();

        if (! $primaryUser instanceof User) {
            return;
        }

        $memo = Memo::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'subject' => 'Directive: Institutional Hierarchy & Centralized Governance Rollout',
        ], [
            'sender_id' => (string) ($ccUser->uuid ?? $primaryUser->uuid),
            'body' => 'All Directorates, Departments, and Units have been mapped to the centralized RBAC and escalation framework effective immediately.',
            'status' => Memo::STATUS_SENT,
            'signed_at' => now()->subHours(4),
            'verified_at' => now()->subHours(4),
            'sent_at' => now()->subHours(4),
        ]);

        MemoRecipient::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'memo_id' => $memo->id,
            'recipient_type' => MemoRecipient::TYPE_USER,
            'recipient_id' => (string) $primaryUser->id,
            'role' => MemoRecipient::ROLE_TO,
        ], [
            'requires_ack' => true,
        ]);

        if ($ccUser instanceof User) {
            MemoRecipient::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'memo_id' => $memo->id,
                'recipient_type' => MemoRecipient::TYPE_USER,
                'recipient_id' => (string) $ccUser->id,
                'role' => MemoRecipient::ROLE_CC,
            ], [
                'requires_ack' => false,
            ]);
        }

        if ($bccUser instanceof User) {
            MemoRecipient::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'memo_id' => $memo->id,
                'recipient_type' => MemoRecipient::TYPE_USER,
                'recipient_id' => (string) $bccUser->id,
                'role' => MemoRecipient::ROLE_BCC,
            ], [
                'requires_ack' => false,
            ]);
        }

        if ($primaryUser->unreadNotifications()->count() === 0) {
            $primaryUser->notifyNow(MemoDispatchedNotification::fromMemo(
                $memo,
                $ccUser?->displayName() ?? 'Executive Directorate',
                MemoRecipient::ROLE_TO
            ), ['database']);
        }

        if (Schema::connection($tenantConnection)->hasTable('forum_messages') && $ccUser instanceof User) {
            $directMessage = ForumMessage::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'subject' => 'Q4 Directorate Budget & Asset Verification Schedule',
            ], [
                'sender_id' => (string) $ccUser->uuid,
                'body' => 'Please review the attached capital allocation and facility inspection schedule prior to the Friday executive briefing.',
            ]);

            ForumMessageRecipient::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'message_id' => $directMessage->id,
                'user_id' => (string) $primaryUser->uuid,
            ], [
                'role' => ForumMessageRecipient::ROLE_TO,
            ]);

            if ($bccUser instanceof User) {
                ForumMessageRecipient::query()->updateOrCreate([
                    'tenant_id' => $tenant->id,
                    'message_id' => $directMessage->id,
                    'user_id' => (string) $bccUser->uuid,
                ], [
                    'role' => ForumMessageRecipient::ROLE_BCC,
                ]);
            }

            if ($primaryUser->unreadNotifications()->count() < 2) {
                $primaryUser->notifyNow(DirectMessageReceivedNotification::fromMessage(
                    $directMessage,
                    $ccUser->displayName(),
                    ForumMessageRecipient::ROLE_TO
                ), ['database']);
            }
        }
    }

    private function seedInstitutionalExpansionModules(Tenant $tenant): void
    {
        $tenantConnection = config('database.default_tenant_connection', 'tenant');
        $primaryUser = User::query()->where('tenant_id', $tenant->id)->first();

        // 1. CRM Leads
        if (Schema::connection($tenantConnection)->hasTable('crm_leads')) {
            \App\Modules\Crm\Models\CrmLead::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'email' => 'kofi.adomako@ghanaenergy.test',
            ], [
                'full_name' => 'Kofi Adomako',
                'reference_code' => 'LEAD-2026-001',
                'phone' => '+233244998877',
                'organisation' => 'Ghana Energy & Infrastructure Corp',
                'source' => \App\Modules\Crm\Models\CrmLead::SOURCE_WALK_IN,
                'status' => \App\Modules\Crm\Models\CrmLead::STATUS_NEW,
                'message' => 'Walk-in inquiry regarding land acquisition for regional sub-station and facilities.',
            ]);
        }

        // 2. Vendor Marketplace & Commission Rate Card
        if (Schema::connection($tenantConnection)->hasTable('vendor_commission_rate_cards')) {
            \App\Modules\VendorMarketplace\Models\CommissionRateCard::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'category' => 'construction_materials',
            ], [
                'label' => 'Construction Materials Standard Split',
                'tenant_commission_bps' => 1000,
                'platform_commission_bps' => 500,
                'is_active' => true,
            ]);
        }

        if (Schema::connection($tenantConnection)->hasTable('vendor_profiles')) {
            \App\Modules\VendorMarketplace\Models\VendorProfile::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'business_name' => 'Tema Aggregates & Cement Supply Co.',
            ], [
                'category' => 'construction_materials',
                'code' => 'VND-001',
                'email' => 'supply@temaaggregates.test',
                'phone' => '+233303221100',
                'license_number' => 'LIC-TMA-2026',
                'status' => 'verified',
                'rating' => 4.9,
            ]);
        }

        // 3. Real Estate Land Listing
        if (Schema::connection($tenantConnection)->hasTable('property_listings')) {
            \App\Modules\RealEstate\Models\PropertyListing::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'slug' => 'shai-hills-light-industrial-enclave-50-acres',
            ], [
                'title' => 'Shai Hills Light Industrial Enclave - 50 Acres',
                'location' => 'Shai Hills, Greater Accra',
                'description' => 'Serviced industrial land with registered site plan and demarcated sub-parcels.',
                'price_pesewas' => 15000000000,
                'size_dimension' => '50 Acres',
                'tenure_type' => 'PRIVATE',
                'compliance_stage' => 'PROJECT_READY_FOR_BUILD',
                'is_published' => true,
            ]);
        }

        // 4. Facilities Management Building, Asset & Work Order
        if (Schema::connection($tenantConnection)->hasTable('facility_buildings')) {
            $building = \App\Modules\FacilitiesManagement\Models\FacilityBuilding::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'code' => 'BLD-HQ-MAIN',
            ], [
                'name' => 'Executive Corporate Headquarters',
                'address' => 'Independence Avenue, Ridge, Accra',
                'floors_count' => 6,
                'total_area_sqm' => 6500.00,
                'manager_id' => $primaryUser?->uuid !== null ? (string) $primaryUser->uuid : null,
            ]);

            if (Schema::connection($tenantConnection)->hasTable('facility_assets')) {
                $asset = \App\Modules\FacilitiesManagement\Models\FacilityAsset::query()->updateOrCreate([
                    'tenant_id' => $tenant->id,
                    'asset_tag' => 'AST-HQ-PWR-001',
                ], [
                    'facility_building_id' => $building->id,
                    'serial_number' => 'CAT-500KVA-001',
                    'name' => 'Caterpillar 500kVA Standby Diesel Generator',
                    'category' => 'electrical',
                    'manufacturer' => 'Caterpillar Energy',
                    'model_number' => 'CAT-C15',
                    'criticality' => 'mission_critical',
                    'status' => 'operational',
                    'purchase_cost_pesewas' => 45000000,
                ]);

                if (Schema::connection($tenantConnection)->hasTable('facility_work_orders')) {
                    \App\Modules\FacilitiesManagement\Models\FacilityWorkOrder::query()->updateOrCreate([
                        'tenant_id' => $tenant->id,
                        'work_order_number' => 'WO-FAC-001',
                    ], [
                        'facility_building_id' => $building->id,
                        'facility_asset_id' => $asset->id,
                        'requester_id' => $primaryUser?->uuid !== null ? (string) $primaryUser->uuid : (string) Str::uuid(),
                        'mode' => \App\Modules\FacilitiesManagement\Models\FacilityWorkOrder::MODE_INSTITUTIONAL,
                        'title' => 'Q4 Scheduled Generator Servicing & Filter Replacement',
                        'description' => 'Routine preventive servicing prior to upcoming dry season load spikes.',
                        'category' => 'electrical',
                        'priority' => 'high',
                        'status' => 'approved',
                    ]);
                }
            }
        }

        // 5. Intranet Events & Message Board
        if (Schema::connection($tenantConnection)->hasTable('intranet_events')) {
            \App\Modules\Intranet\Models\IntranetEvent::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'title' => 'Institutional Strategy & Q4 Governance Review',
            ], [
                'description' => 'Annual strategic operational performance audit and cross-directorate alignment.',
                'event_date' => now()->addDays(14)->toDateString(),
                'start_time' => '09:00:00',
                'end_time' => '16:00:00',
                'location' => 'Main Executive Boardroom & Hybrid Conference',
                'category' => 'executive',
                'is_virtual' => true,
                'organizer_id' => $primaryUser?->uuid !== null ? (string) $primaryUser->uuid : null,
            ]);
        }

        if (Schema::connection($tenantConnection)->hasTable('intranet_message_boards')) {
            \App\Modules\Intranet\Models\IntranetMessageBoard::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'slug' => 'general-announcements',
            ], [
                'title' => 'General Announcements & Executive Notices',
                'description' => 'Open institutional forum for leadership updates, policies, and staff commendations.',
                'is_pinned' => true,
            ]);
        }

        // 6. Forms & Surveys
        if (Schema::connection($tenantConnection)->hasTable('form_definitions')) {
            $survey = \App\Modules\Forms\Models\FormDefinition::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'slug' => 'staff-satisfaction-survey-2026',
            ], [
                'title' => 'Staff Engagement & Workplace Satisfaction Survey',
                'description' => 'Annual institutional survey evaluating workplace tools, team culture, and leadership support.',
                'type' => 'survey',
                'target_audience' => 'internal_only',
                'status' => 'published',
                'single_response_per_user' => true,
                'created_by' => $primaryUser?->uuid !== null ? (string) $primaryUser->uuid : (string) Str::uuid(),
            ]);

            if (Schema::connection($tenantConnection)->hasTable('form_fields')) {
                \App\Modules\Forms\Models\FormField::query()->updateOrCreate([
                    'tenant_id' => $tenant->id,
                    'form_definition_id' => $survey->id,
                    'field_name' => 'overall_rating',
                ], [
                    'label' => 'Overall Satisfaction',
                    'field_type' => 'select',
                    'options_json' => ['Very Satisfied', 'Satisfied', 'Neutral', 'Dissatisfied'],
                    'is_required' => true,
                    'sort_order' => 1,
                ]);
            }

            $poll = \App\Modules\Forms\Models\FormDefinition::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'slug' => 'annual-retreat-venue-poll',
            ], [
                'title' => 'Executive Retreat Venue Selection Poll',
                'description' => 'Cast your preference for the upcoming senior leadership strategy session.',
                'type' => 'poll',
                'target_audience' => 'internal_only',
                'status' => 'published',
                'single_response_per_user' => true,
                'created_by' => $primaryUser?->uuid !== null ? (string) $primaryUser->uuid : (string) Str::uuid(),
            ]);

            if (Schema::connection($tenantConnection)->hasTable('form_fields')) {
                \App\Modules\Forms\Models\FormField::query()->updateOrCreate([
                    'tenant_id' => $tenant->id,
                    'form_definition_id' => $poll->id,
                    'field_name' => 'retreat_choice',
                ], [
                    'label' => 'Preferred Venue',
                    'field_type' => 'radio',
                    'options_json' => ['Peduase Valley Resort', 'Aqua Safari Resort - Ada', 'Royal Senchi Hotel - Akosombo'],
                    'is_required' => true,
                    'sort_order' => 1,
                ]);
            }
        }

        // 7. Store Platform Credits & Products
        if (Schema::hasTable('platform_credit_packages')) {
            \App\Modules\Store\Models\PlatformCreditPackage::query()->updateOrCreate([
                'name' => 'SMS Enterprise Bundle (5,000 SMS)',
            ], [
                'credit_type' => 'sms',
                'amount_credits' => 5000,
                'price_cents' => 60000,
                'currency' => 'GHS',
                'is_active' => true,
            ]);

            \App\Modules\Store\Models\PlatformCreditPackage::query()->updateOrCreate([
                'name' => 'AI Intelligence Quota (100,000 Tokens)',
            ], [
                'credit_type' => 'ai_tokens',
                'amount_credits' => 100000,
                'price_cents' => 85000,
                'currency' => 'GHS',
                'is_active' => true,
            ]);
        }

        if (Schema::hasTable('tenant_credit_wallets')) {
            \App\Modules\Store\Models\TenantCreditWallet::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'credit_type' => 'sms',
            ], [
                'balance' => 5000,
                'total_purchased' => 5000,
                'total_consumed' => 0,
            ]);

            \App\Modules\Store\Models\TenantCreditWallet::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'credit_type' => 'ai_tokens',
            ], [
                'balance' => 250000,
                'total_purchased' => 250000,
                'total_consumed' => 0,
            ]);
        }

        if (Schema::connection($tenantConnection)->hasTable('store_products')) {
            \App\Modules\Store\Models\StoreProduct::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'slug' => 'branded-executive-polo-shirt',
            ], [
                'name' => 'Branded Executive Polo Shirt',
                'description' => 'Breathable institutional corporate polo shirt with embroidered emblem.',
                'price_cents' => 18000,
                'currency' => 'GHS',
                'stock_quantity' => 50,
                'is_active' => true,
            ]);
        }

        // 8. Reports Definition
        if (Schema::connection($tenantConnection)->hasTable('report_definitions')) {
            \App\Modules\Reports\Models\ReportDefinition::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'name' => 'Institutional Safety & Incidents Audit',
            ], [
                'description' => 'Operational audit report covering all safety incidents, impact classification, and reporting channels.',
                'data_source' => 'incidents',
                'selected_columns' => ['reference_code', 'title', 'impact', 'reported_via'],
                'is_public' => true,
                'created_by_user_id' => $primaryUser?->uuid !== null ? (string) $primaryUser->uuid : null,
            ]);
        }

        // 9. Workflow Automation Rule
        if (Schema::connection($tenantConnection)->hasTable('workflow_rules')) {
            \App\Modules\WorkflowAutomation\Models\WorkflowRule::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'name' => 'Critical Incident Immediate Leadership Notification',
            ], [
                'description' => 'Automatically alerts executive leadership whenever a high or critical incident is logged.',
                'trigger_event' => 'incident.logged',
                'conditions' => [
                    ['field' => 'impact', 'operator' => '=', 'value' => 'critical'],
                ],
                'actions' => [
                    [
                        'type' => 'dispatch_notification',
                        'target_role' => 'Super Admin',
                        'title_template' => 'URGENT: {title}',
                        'body_template' => 'A critical incident ({title}) was recorded. Immediate intervention is requested.',
                    ],
                ],
                'is_active' => true,
                'created_by_user_id' => $primaryUser?->uuid !== null ? (string) $primaryUser->uuid : null,
            ]);
        }
    }
}
