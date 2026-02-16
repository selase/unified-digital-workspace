<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;

it('renders core admin metronic routes with shell interaction hooks', function (): void {
    $user = User::factory()->create();
    $role = Role::firstOrCreate([
        'name' => 'Superadmin',
        'guard_name' => 'web',
    ]);

    $permissions = [
        'access dashboard' => 'dashboard',
        'read user' => 'user',
        'read tenant' => 'tenant',
        'read audit-trail' => 'audit-trail',
    ];

    foreach ($permissions as $permissionName => $category) {
        Permission::firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'web',
        ], [
            'uuid' => (string) Str::uuid(),
            'category' => $category,
        ]);
    }

    setPermissionsTeamId(null);
    $user->assignRole($role);
    $user->givePermissionTo(array_keys($permissions));

    $routes = [
        route('dashboard'),
        route('users.index'),
        route('roles.index'),
        route('tenants.index'),
        route('audit-trail.activity-logs.index'),
        route('audit-trail.login-history.index'),
    ];

    foreach ($routes as $url) {
        $content = $this->actingAs($user)->get($url)->assertSuccessful()->getContent();

        expect($content)->toContain('assets/metronic/css/styles.css');
        expect($content)->toContain('data-kt-drawer-toggle="#sidebar"');
        expect($content)->toContain('data-kt-modal-toggle="#search_modal"');
        expect($content)->toContain('data-kt-dropdown-toggle="true"');
    }

    $dashboardContent = $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->getContent();

    expect($dashboardContent)->toContain(route('tenants.index'));
    expect($dashboardContent)->toContain(route('features.index'));
    expect($dashboardContent)->toContain(route('packages.index'));
    expect($dashboardContent)->toContain(route('admin.billing.transactions.index'));
    expect($dashboardContent)->toContain(route('admin.leads.index'));
    expect($dashboardContent)->toContain(route('settings.developer.tokens.index'));
    expect($dashboardContent)->toContain(route('health.tenants'));
    expect($dashboardContent)->toContain(route('llm-usage.index'));
    expect($dashboardContent)->toContain(route('tenant.my-tenants'));
    expect($dashboardContent)->toContain(route('logout'));
    expect($dashboardContent)->toContain('Log out');
});

it('renders tenant dashboard with metronic shell hooks and without legacy bootstrap modal classes', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Permission::firstOrCreate([
        'name' => 'access dashboard',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
        'category' => 'dashboard',
    ]);

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo('access dashboard');

    $content = $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('tenant.dashboard', ['subdomain' => $tenant->slug]))
        ->assertSuccessful()
        ->getContent();

    expect($content)->toContain('assets/metronic/css/styles.css');
    expect($content)->toContain('data-kt-drawer-toggle="#sidebar"');
    expect($content)->not->toContain('modal fade');
    expect($content)->not->toContain('btn btn-');
    expect($content)->toContain(route('tenant.users.index', ['subdomain' => $tenant->slug]));
    expect($content)->toContain(route('tenant.roles.index', ['subdomain' => $tenant->slug]));
    expect($content)->toContain(route('tenant.settings.index', ['subdomain' => $tenant->slug]));
    expect($content)->toContain(route('tenant.settings.billing', ['subdomain' => $tenant->slug]));
    expect($content)->toContain(route('tenant.settings.payments.index', ['subdomain' => $tenant->slug]));
    expect($content)->toContain(route('tenant.finance.index', ['subdomain' => $tenant->slug]));
    expect($content)->toContain(route('tenant.api-keys.index', ['subdomain' => $tenant->slug]));
    expect($content)->toContain(route('tenant.llm-usage.index', ['subdomain' => $tenant->slug]));
    expect($content)->toContain(route('tenant.llm-config.index', ['subdomain' => $tenant->slug]));
    expect($content)->toContain(route('tenant.pricing', ['subdomain' => $tenant->slug]));
    expect($content)->toContain(route('tenant.my-tenants'));
});

it('renders auth pages with metronic assets', function (): void {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('assets/metronic/css/styles.css');

    $this->get(route('password.request'))
        ->assertSuccessful()
        ->assertSee('assets/metronic/css/styles.css');
});

it('switches top menu context when a tenant module page is active', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Permission::firstOrCreate([
        'name' => 'qm.workplans.view',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
        'category' => 'quality-monitoring',
    ]);

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo('qm.workplans.view');

    app(App\Services\ModuleManager::class)->enableForTenant('quality-monitoring', $tenant);

    $content = $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('quality-monitoring.index'))
        ->assertSuccessful()
        ->getContent();

    expect($content)->toContain('Quality Monitoring');
    expect($content)->toContain('Quality Ops');
    expect($content)->toContain('Reports');
    expect($content)->toContain('Workplans API');
});

it('shows incident module sidebar and top menu links on incident pages', function (): void {
    $user = User::factory()->create();
    [$tenant] = setupIncidentTenantConnection($user);

    Permission::firstOrCreate([
        'name' => 'incidents.view',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
        'category' => 'incident-management',
    ]);

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo('incidents.view');

    app(App\Services\ModuleManager::class)->enableForTenant('incident-management', $tenant);

    $content = $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('incident-management.index'))
        ->assertSuccessful()
        ->getContent();

    expect($content)->toContain(route('incident-management.incidents.index'));
    expect($content)->toContain(route('incident-management.tasks.index'));
    expect($content)->toContain(route('incident-management.reports.index'));
    expect($content)->toContain('Operations');
});

it('shows hrms contextual menu and page links on hrms routes', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    foreach ([
        'hrms.employees.view',
        'hrms.departments.view',
        'hrms.leave.view',
        'hrms.jobs.view',
    ] as $permissionName) {
        Permission::firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'web',
        ], [
            'uuid' => (string) Str::uuid(),
            'category' => 'hrms',
        ]);
    }

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo([
        'hrms.employees.view',
        'hrms.departments.view',
        'hrms.leave.view',
        'hrms.jobs.view',
    ]);

    app(App\Services\ModuleManager::class)->enableForTenant('hrms-core', $tenant);

    $content = $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('hrms-core.index'))
        ->assertSuccessful()
        ->getContent();

    expect($content)->toContain(route('hrms-core.employees.index'));
    expect($content)->toContain(route('hrms-core.departments.index'));
    expect($content)->toContain(route('hrms-core.leave-requests.index'));
    expect($content)->toContain(route('hrms-core.recruitment.index'));
    expect($content)->toContain('People Ops');
});

it('shows cms contextual menu and page links on cms routes', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    foreach ([
        'cms.posts.view',
        'cms.media.view',
        'cms.menus.view',
    ] as $permissionName) {
        Permission::firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'web',
        ], [
            'uuid' => (string) Str::uuid(),
            'category' => 'cms',
        ]);
    }

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo([
        'cms.posts.view',
        'cms.media.view',
        'cms.menus.view',
    ]);

    app(App\Services\ModuleManager::class)->enableForTenant('cms-core', $tenant);

    $content = $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('cms-core.index'))
        ->assertSuccessful()
        ->getContent();

    expect($content)->toContain(route('cms-core.posts.index'));
    expect($content)->toContain(route('cms-core.media.index'));
    expect($content)->toContain(route('cms-core.menus.index'));
    expect($content)->toContain('Publishing');
});

it('shows forums module links in tenant shell when forums module is enabled', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    foreach ([
        'forums.view',
        'forums.moderate',
    ] as $permissionName) {
        Permission::firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'web',
        ], [
            'uuid' => (string) Str::uuid(),
            'category' => 'forums',
        ]);
    }

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo([
        'forums.view',
        'forums.moderate',
    ]);

    app(App\Services\ModuleManager::class)->enableForTenant('forums', $tenant);

    $content = $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('tenant.dashboard', ['subdomain' => $tenant->slug]))
        ->assertSuccessful()
        ->getContent();

    expect($content)->toContain(route('forums.channels.index'));
    expect($content)->toContain(route('forums.threads.index'));
    expect($content)->toContain(route('forums.messages.index'));
    expect($content)->toContain(route('forums.moderation.index'));
});
