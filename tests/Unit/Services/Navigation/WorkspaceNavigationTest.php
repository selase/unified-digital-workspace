<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Services\ModuleManager;
use App\Services\Navigation\WorkspaceNavigation;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->tenant = setActiveTenantForTest($this->user);
});

test('forRequest returns correct structure for tenant context', function (): void {
    $navigation = buildNavigation($this->user, $this->tenant);

    expect($navigation)
        ->toBeArray()
        ->toHaveKeys(['homeUrl', 'isTenantContext', 'activeModule', 'sidebar', 'topMenuGroups'])
        ->and($navigation['isTenantContext'])->toBeTrue()
        ->and($navigation['activeModule'])->toBeNull()
        ->and($navigation['sidebar'])->toHaveKeys(['dashboards', 'superadmin', 'modules', 'tenant_groups'])
        ->and($navigation['sidebar']['dashboards'])->toBeArray()
        ->and($navigation['sidebar']['tenant_groups'])->toBeArray();
});

test('forRequest returns correct structure without tenant context', function (): void {
    // Bind a fresh TenantContext with no tenant set
    $freshContext = new TenantContext();
    app()->instance(TenantContext::class, $freshContext);

    $request = Request::create('/dashboard');
    $request->setUserResolver(fn () => $this->user);

    try {
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));
    } catch (Throwable) {
        // Route may not match
    }

    $navigation = app(WorkspaceNavigation::class)->forRequest($request);

    expect($navigation['isTenantContext'])->toBeFalse()
        ->and($navigation['sidebar']['tenant_groups'])->toBeEmpty()
        ->and($navigation['sidebar']['modules'])->toBeEmpty();
});

test('enabled modules appear in sidebar', function (): void {
    enableModuleWithPermission('hrms-core', 'hrms.employees.view', $this->tenant, $this->user);

    $navigation = buildNavigation($this->user, $this->tenant, '/hrms-core');

    $moduleSlugs = collect($navigation['sidebar']['modules'])->pluck('slug')->all();

    expect($moduleSlugs)->toContain('hrms-core');
});

test('disabled modules do not appear in sidebar', function (): void {
    $navigation = buildNavigation($this->user, $this->tenant);

    $moduleSlugs = collect($navigation['sidebar']['modules'])->pluck('slug')->all();

    expect($moduleSlugs)->not->toContain('hrms-core');
});

test('active module is detected from route name', function (): void {
    enableModuleWithPermission('hrms-core', 'hrms.employees.view', $this->tenant, $this->user);

    $navigation = buildNavigation($this->user, $this->tenant, '/hrms-core/employees');

    expect($navigation['activeModule'])->not->toBeNull()
        ->and($navigation['activeModule']['slug'])->toBe('hrms-core')
        ->and($navigation['activeModule']['is_active'])->toBeTrue();
});

test('module sidebar items include correct labels', function (): void {
    enableModuleWithPermission('hrms-core', 'hrms.employees.view', $this->tenant, $this->user);

    $navigation = buildNavigation($this->user, $this->tenant, '/hrms-core');

    $hrmsModule = collect($navigation['sidebar']['modules'])->firstWhere('slug', 'hrms-core');

    expect($hrmsModule)->not->toBeNull()
        ->and($hrmsModule['label'])->toBe('HRMS')
        ->and($hrmsModule['icon'])->toBe('ki-filled ki-people')
        ->and(collect($hrmsModule['items'])->pluck('label')->all())->toContain('Overview');
});

test('top menu groups populate from active module', function (): void {
    enableModuleWithPermission('hrms-core', 'hrms.employees.view', $this->tenant, $this->user);

    $navigation = buildNavigation($this->user, $this->tenant, '/hrms-core/employees');

    $topGroupLabels = collect($navigation['topMenuGroups'])->pluck('label')->all();

    expect($topGroupLabels)->toContain('Core');
});

test('top menu falls back to workspace groups when no active module', function (): void {
    $navigation = buildNavigation($this->user, $this->tenant);

    $topGroupLabels = collect($navigation['topMenuGroups'])->pluck('label')->all();

    expect($topGroupLabels)->toContain('Workspace');
});

test('permission-gated sidebar items are hidden for unauthorized users', function (): void {
    enableModuleWithPermission('hrms-core', 'hrms.employees.view', $this->tenant, $this->user);

    $navigation = buildNavigation($this->user, $this->tenant, '/hrms-core');

    $hrmsModule = collect($navigation['sidebar']['modules'])->firstWhere('slug', 'hrms-core');
    $labels = collect($hrmsModule['items'])->pluck('label')->all();

    // User only has hrms.employees.view, not hrms.employees.create
    expect($labels)->not->toContain('Add Employee');
});

test('multiple modules appear in sidebar when all enabled', function (): void {
    enableModuleWithPermission('hrms-core', 'hrms.employees.view', $this->tenant, $this->user);
    enableModuleWithPermission('cms-core', 'cms.posts.view', $this->tenant, $this->user);
    enableModuleWithPermission('incident-management', 'incidents.view', $this->tenant, $this->user);

    $navigation = buildNavigation($this->user, $this->tenant);

    $moduleSlugs = collect($navigation['sidebar']['modules'])->pluck('slug')->all();

    expect($moduleSlugs)
        ->toContain('hrms-core')
        ->toContain('cms-core')
        ->toContain('incident-management');
});

test('homeUrl points to tenant dashboard in tenant context', function (): void {
    $navigation = buildNavigation($this->user, $this->tenant);

    expect($navigation['homeUrl'])->toContain('dashboard');
});

// --- Helpers ---

function buildNavigation(User $user, Tenant $tenant, string $path = '/dashboard'): array
{
    $request = Request::create($path);
    $request->setUserResolver(fn () => $user);

    try {
        $route = Route::getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
    } catch (Throwable) {
        // Route may not match in unit context; resolve anyway
    }

    return app(WorkspaceNavigation::class)->forRequest($request);
}

function enableModuleWithPermission(string $slug, string $permissionName, Tenant $tenant, User $user): void
{
    $permission = Permission::firstOrCreate([
        'name' => $permissionName,
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Illuminate\Support\Str::uuid(),
        'category' => explode('.', $slug)[0],
    ]);

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo($permission);

    app(ModuleManager::class)->enableForTenant($slug, $tenant);
}
