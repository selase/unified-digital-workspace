<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\CmsCore\Database\Factories\MediaFactory;
use App\Modules\CmsCore\Database\Factories\PostFactory;
use App\Modules\CmsCore\Models\Menu;
use App\Modules\DocumentManagement\Models\Document;
use App\Modules\DocumentManagement\Models\DocumentQuiz;
use App\Modules\DocumentManagement\Models\DocumentQuizAttempt;
use App\Modules\HrmsCore\Database\Factories\EmployeeFactory;
use App\Modules\HrmsCore\Database\Factories\LeaveCategoryFactory;
use App\Modules\HrmsCore\Database\Factories\LeaveRequestFactory;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Recruitment\JobPosting;
use App\Modules\HrmsCore\Models\Recruitment\JobRequisition;
use App\Modules\IncidentManagement\Models\Incident;
use App\Modules\Memos\Models\Memo;
use App\Services\ModuleManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: Tenant, 1: string}
 */
function setupDocumentWebTenant(User $user): array
{
    $tenantDb = database_path('tenant_documents_web_testing.sqlite');
    if (file_exists($tenantDb)) {
        unlink($tenantDb);
    }
    touch($tenantDb);

    Config::set('database.connections.tenant', [
        'driver' => 'sqlite',
        'database' => $tenantDb,
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);
    Config::set('database.default_tenant_connection', 'tenant');

    DB::purge('tenant');
    DB::reconnect('tenant');

    $tenant = setActiveTenantForTest($user, [
        'isolation_mode' => 'db_per_tenant',
        'db_driver' => 'sqlite',
        'meta' => [
            'database' => $tenantDb,
        ],
    ]);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/DocumentManagement/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

    return [$tenant, $tenantDb];
}

/**
 * @return array{0: Tenant, 1: string}
 */
function setupHrmsWebTenant(User $user): array
{
    $tenantDb = database_path('tenant_hrms_web_testing.sqlite');
    if (file_exists($tenantDb)) {
        unlink($tenantDb);
    }
    touch($tenantDb);

    Config::set('database.connections.tenant', [
        'driver' => 'sqlite',
        'database' => $tenantDb,
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);
    Config::set('database.default_tenant_connection', 'tenant');

    DB::purge('tenant');
    DB::reconnect('tenant');

    $tenant = setActiveTenantForTest($user, [
        'isolation_mode' => 'db_per_tenant',
        'db_driver' => 'sqlite',
        'meta' => [
            'database' => $tenantDb,
        ],
    ]);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/HrmsCore/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

    return [$tenant, $tenantDb];
}

/**
 * @return array{0: Tenant, 1: string}
 */
function setupCmsWebTenant(User $user): array
{
    $tenantDb = database_path('tenant_cms_web_testing.sqlite');
    if (file_exists($tenantDb)) {
        unlink($tenantDb);
    }
    touch($tenantDb);

    Config::set('database.connections.tenant', [
        'driver' => 'sqlite',
        'database' => $tenantDb,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    Config::set('database.default_tenant_connection', 'tenant');

    DB::purge('tenant');
    DB::reconnect('tenant');

    $tenant = setActiveTenantForTest($user, [
        'isolation_mode' => 'db_per_tenant',
        'db_driver' => 'sqlite',
        'meta' => [
            'database' => $tenantDb,
        ],
    ]);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/CmsCore/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

    return [$tenant, $tenantDb];
}

test('document management web hub renders for enabled tenant module', function (): void {
    $user = User::factory()->create();
    [$tenant] = setupDocumentWebTenant($user);

    Permission::firstOrCreate([
        'name' => 'documents.view',
        'category' => 'documents',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
    ]);

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo('documents.view');

    app(ModuleManager::class)->enableForTenant('document-management', $tenant);

    Document::create([
        'title' => 'Operations Handbook',
        'slug' => 'operations-handbook',
        'owner_id' => (string) $user->uuid,
        'status' => 'published',
    ]);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/document-management')
        ->assertSuccessful()
        ->assertSee('Document Management Hub')
        ->assertSee('Operations Handbook')
        ->assertSee('assets/metronic/css/styles.css');

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/document-management/documents')
        ->assertSuccessful()
        ->assertSee('Document Library')
        ->assertSee('Operations Handbook');
});

test('document quiz analytics page is manager-only', function (): void {
    $user = User::factory()->create();
    [$tenant] = setupDocumentWebTenant($user);

    Permission::firstOrCreate([
        'name' => 'documents.view',
        'category' => 'documents',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
    ]);
    Permission::firstOrCreate([
        'name' => 'documents.manage_quizzes',
        'category' => 'documents',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
    ]);

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo('documents.view');

    app(ModuleManager::class)->enableForTenant('document-management', $tenant);

    $document = Document::create([
        'title' => 'Safety Policy',
        'slug' => 'safety-policy',
        'owner_id' => (string) $user->uuid,
        'status' => 'published',
    ]);

    $quiz = DocumentQuiz::create([
        'document_id' => $document->id,
        'title' => 'Safety Quiz',
        'settings' => ['pass_score' => 2],
    ]);

    DocumentQuizAttempt::create([
        'quiz_id' => $quiz->id,
        'user_id' => (string) $user->uuid,
        'score' => 2,
        'responses' => [],
        'completed_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/document-management/analytics')
        ->assertForbidden();

    $user->givePermissionTo('documents.manage_quizzes');

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/document-management/analytics')
        ->assertSuccessful()
        ->assertSee('Quiz Analytics')
        ->assertSee('Safety Quiz');

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/document-management/quizzes')
        ->assertSuccessful()
        ->assertSee('Quiz Library')
        ->assertSee('Safety Quiz');
});

test('document audit page requires audit permission', function (): void {
    $user = User::factory()->create();
    [$tenant] = setupDocumentWebTenant($user);

    Permission::firstOrCreate([
        'name' => 'documents.view',
        'category' => 'documents',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
    ]);
    Permission::firstOrCreate([
        'name' => 'documents.audit.view',
        'category' => 'documents',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
    ]);

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo('documents.view');

    app(ModuleManager::class)->enableForTenant('document-management', $tenant);

    $document = Document::create([
        'title' => 'Audit Policy',
        'slug' => 'audit-policy',
        'owner_id' => (string) $user->uuid,
        'status' => 'published',
    ]);

    $document->audits()->create([
        'user_id' => (string) $user->uuid,
        'event' => 'viewed',
        'metadata' => ['source' => 'test'],
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/document-management/audits')
        ->assertForbidden();

    $user->givePermissionTo('documents.audit.view');

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/document-management/audits')
        ->assertSuccessful()
        ->assertSee('Audit Timeline')
        ->assertSee('viewed');
});

test('memos web hub renders for enabled tenant module', function (): void {
    $user = User::factory()->create();
    [$tenant] = setupMemoTenantConnection($user);

    Permission::firstOrCreate([
        'name' => 'memos.view',
        'category' => 'memos',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
    ]);

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo('memos.view');

    app(ModuleManager::class)->enableForTenant('memos', $tenant);

    Memo::create([
        'sender_id' => (string) $user->id,
        'subject' => 'Q1 Budget Memo',
        'body' => 'Please review the quarterly budget memo.',
        'status' => Memo::STATUS_SENT,
        'sent_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/memos')
        ->assertSuccessful()
        ->assertSee('Memos Hub')
        ->assertSee('Q1 Budget Memo')
        ->assertSee('assets/metronic/css/styles.css');
});

test('incident management web hub renders for enabled tenant module', function (): void {
    $user = User::factory()->create();
    [$tenant] = setupIncidentTenantConnection($user);

    Permission::firstOrCreate([
        'name' => 'incidents.view',
        'category' => 'incident-management',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
    ]);

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo('incidents.view');

    app(ModuleManager::class)->enableForTenant('incident-management', $tenant);

    $incident = Incident::create([
        'title' => 'Internet outage in Accounts',
        'description' => 'Accounts team cannot access the intranet and ERP.',
        'reported_by_id' => (string) $user->uuid,
    ]);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/incident-management')
        ->assertSuccessful()
        ->assertSee('Incident Management Hub')
        ->assertSee($incident->title)
        ->assertSee('assets/metronic/css/styles.css');

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/incident-management/incidents')
        ->assertSuccessful()
        ->assertSee('Incident Register')
        ->assertSee($incident->reference_code);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/incident-management/tasks')
        ->assertSuccessful()
        ->assertSee('Task Board');

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/incident-management/reports')
        ->assertSuccessful()
        ->assertSee('Progress Reports');
});

test('hrms core web hub renders for enabled tenant module', function (): void {
    $user = User::factory()->create();
    [$tenant] = setupHrmsWebTenant($user);

    foreach ([
        'hrms.employees.view',
        'hrms.departments.view',
        'hrms.leave.view',
        'hrms.jobs.view',
    ] as $permissionName) {
        Permission::firstOrCreate([
            'name' => $permissionName,
            'category' => 'hrms',
            'guard_name' => 'web',
        ], [
            'uuid' => (string) Str::uuid(),
        ]);
    }

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo([
        'hrms.employees.view',
        'hrms.departments.view',
        'hrms.leave.view',
        'hrms.jobs.view',
    ]);

    app(ModuleManager::class)->enableForTenant('hrms-core', $tenant);

    $employee = EmployeeFactory::new()->forTenant($tenant->id)->create([
        'first_name' => 'Jane',
        'last_name' => 'Mensah',
    ]);
    $department = Department::create([
        'tenant_id' => $tenant->id,
        'name' => 'Operations',
        'slug' => 'operations',
        'is_active' => true,
    ]);
    $category = LeaveCategoryFactory::new()->forTenant($tenant->id)->create([
        'name' => 'Annual Leave',
    ]);
    LeaveRequestFactory::new()
        ->forTenant($tenant->id)
        ->forEmployee($employee)
        ->forCategory($category)
        ->create();

    $requisition = JobRequisition::create([
        'tenant_id' => $tenant->id,
        'title' => 'Support Analyst',
        'department_id' => $department->id,
        'employment_type' => 'full_time',
        'vacancies' => 1,
        'status' => 'open',
    ]);
    JobPosting::create([
        'tenant_id' => $tenant->id,
        'requisition_id' => $requisition->id,
        'title' => 'Support Analyst',
    ]);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/hrms-core')
        ->assertSuccessful()
        ->assertSee('HRMS Hub')
        ->assertSee('assets/metronic/css/styles.css');

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/hrms-core/employees')
        ->assertSuccessful()
        ->assertSee('Employee Directory')
        ->assertSee('Jane Mensah');

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/hrms-core/departments')
        ->assertSuccessful()
        ->assertSee('Department Directory')
        ->assertSee('Operations');

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/hrms-core/leave-requests')
        ->assertSuccessful()
        ->assertSee('Leave Requests')
        ->assertSee('Annual Leave');

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/hrms-core/recruitment')
        ->assertSuccessful()
        ->assertSee('Recruitment Pipeline')
        ->assertSee('Support Analyst');
});

test('cms core web hub renders for enabled tenant module', function (): void {
    $user = User::factory()->create();
    [$tenant] = setupCmsWebTenant($user);

    foreach ([
        'cms.posts.view',
        'cms.media.view',
        'cms.menus.view',
    ] as $permissionName) {
        Permission::firstOrCreate([
            'name' => $permissionName,
            'category' => 'cms',
            'guard_name' => 'web',
        ], [
            'uuid' => (string) Str::uuid(),
        ]);
    }

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo([
        'cms.posts.view',
        'cms.media.view',
        'cms.menus.view',
    ]);

    app(ModuleManager::class)->enableForTenant('cms-core', $tenant);

    PostFactory::new()->forTenant($tenant->id)->create([
        'author_id' => $user->id,
        'title' => 'Policy Update',
        'slug' => 'policy-update',
    ]);
    MediaFactory::new()->forTenant($tenant->id)->create([
        'uploaded_by' => $user->id,
        'filename' => 'policy-banner.jpg',
        'path' => 'media/policy-banner.jpg',
    ]);
    Menu::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Navigation',
        'slug' => 'main-navigation',
    ]);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/cms-core')
        ->assertSuccessful()
        ->assertSee('CMS Hub')
        ->assertSee('assets/metronic/css/styles.css');

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/cms-core/posts')
        ->assertSuccessful()
        ->assertSee('Post Library')
        ->assertSee('Policy Update');

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/cms-core/media')
        ->assertSuccessful()
        ->assertSee('Media Library')
        ->assertSee('policy-banner.jpg');

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/cms-core/menus')
        ->assertSuccessful()
        ->assertSee('Menu Registry')
        ->assertSee('Main Navigation');
});

test('project management web hub renders for enabled tenant module', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Permission::firstOrCreate([
        'name' => 'projects.view',
        'category' => 'project-management',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
    ]);

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo('projects.view');

    app(ModuleManager::class)->enableForTenant('project-management', $tenant);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/project-management')
        ->assertSuccessful()
        ->assertSee('Project Management Hub')
        ->assertSee('assets/metronic/css/styles.css');
});

test('quality monitoring web hub renders for enabled tenant module', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Permission::firstOrCreate([
        'name' => 'qm.workplans.view',
        'category' => 'quality-monitoring',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
    ]);

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo('qm.workplans.view');

    app(ModuleManager::class)->enableForTenant('quality-monitoring', $tenant);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/quality-monitoring')
        ->assertSuccessful()
        ->assertSee('Quality Monitoring Hub')
        ->assertSee('assets/metronic/css/styles.css');
});
