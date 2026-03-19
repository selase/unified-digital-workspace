<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Services\ModuleManager;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->tenant = setActiveTenantForTest($this->user);

    Permission::firstOrCreate([
        'name' => 'access dashboard',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
        'category' => 'dashboard',
    ]);

    setPermissionsTeamId($this->tenant->id);
    $this->user->givePermissionTo('access dashboard');
});

test('shell renders sidebar with metronic classes for authenticated user', function (): void {
    $this->actingAs($this->user)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertSee('id="sidebar"', false)
        ->assertSee('id="sidebar_menu"', false)
        ->assertSee('kt-menu-item', false)
        ->assertSee('assets/metronic/css/styles.css', false);
});

test('sidebar shows dashboard section for authenticated user', function (): void {
    $this->actingAs($this->user)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertSee('Dashboards');
});

test('sidebar shows tenant section in tenant context', function (): void {
    $this->actingAs($this->user)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertSeeInOrder(['Dashboards', 'Tenant']);
});

test('sidebar module accordion gets here show classes when module is active', function (): void {
    shellGrantModuleAccess('project-management', 'projects.view', $this->tenant, $this->user);

    $response = $this->actingAs($this->user)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->get('/project-management/hub');

    $response->assertSuccessful();

    $html = $response->getContent();

    expect($html)->toContain('kt-menu-item here show');
});

test('sidebar module accordion does not get here show when not active', function (): void {
    shellGrantModuleAccess('project-management', 'projects.view', $this->tenant, $this->user);

    $response = $this->actingAs($this->user)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->get('/dashboard');

    $response->assertSuccessful();

    $html = $response->getContent();

    // On dashboard, no module accordion should have 'here show'
    expect(mb_substr_count($html, 'kt-menu-item here show'))->toBe(0);
});

test('active sidebar link uses metronic active class pattern', function (): void {
    $response = $this->actingAs($this->user)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->get('/dashboard');

    $response->assertSuccessful();

    $html = $response->getContent();

    expect($html)->toContain('kt-menu-item-active:bg-accent/60')
        ->and($html)->toContain('kt-menu-item-active:before:bg-primary')
        ->and($html)->toContain('kt-menu-item-active:text-primary');
});

test('top menu shows module context groups when module is active', function (): void {
    shellGrantModuleAccess('project-management', 'projects.view', $this->tenant, $this->user);

    $response = $this->actingAs($this->user)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->get('/project-management/hub');

    $response->assertSuccessful()
        ->assertSee('Project Management')
        ->assertSee('id="mega_menu"', false);

    $html = $response->getContent();

    // Top menu group with active item should have 'here' class for highlighting
    expect($html)->toContain('kt-menu-item-here:text-primary');
});

test('top right controls render in header', function (): void {
    $this->actingAs($this->user)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertSee('id="search_modal"', false)
        ->assertSee('id="notifications_drawer"', false)
        ->assertSee('id="chat_drawer"', false);
});

test('profile dropdown shows user info and logout', function (): void {
    $this->actingAs($this->user)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertSee($this->user->email)
        ->assertSee('Log out')
        ->assertSee('Dark Mode');
});

test('theme switcher toggle renders in profile dropdown', function (): void {
    $response = $this->actingAs($this->user)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->get('/dashboard');

    $response->assertSuccessful();

    $html = $response->getContent();

    expect($html)->toContain('data-kt-theme-switch-toggle="true"');
});

test('mobile hamburger button renders for sidebar drawer', function (): void {
    $response = $this->actingAs($this->user)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->get('/dashboard');

    $response->assertSuccessful();

    $html = $response->getContent();

    expect($html)->toContain('data-kt-drawer-toggle="#sidebar"');
});

test('module badge shows in header when module is active', function (): void {
    shellGrantModuleAccess('project-management', 'projects.view', $this->tenant, $this->user);

    $this->actingAs($this->user)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->get('/project-management/hub')
        ->assertSuccessful()
        ->assertSee('kt-badge kt-badge-outline', false);
});

test('workspace label shows when no module is active', function (): void {
    $this->actingAs($this->user)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertSee('Workspace');
});

test('stale overlay cleanup script is present in shell', function (): void {
    $response = $this->actingAs($this->user)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->get('/dashboard');

    $response->assertSuccessful();

    $html = $response->getContent();

    expect($html)->toContain('clearStaleUiOverlays')
        ->and($html)->toContain('forceResetUiLayers')
        ->and($html)->toContain('normalizeWorkspaceChrome');
});

test('sidebar normalization guards against mobile flash with media query', function (): void {
    $response = $this->actingAs($this->user)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->get('/dashboard');

    $response->assertSuccessful();

    $html = $response->getContent();

    // Sidebar hidden-class removal must only happen on desktop (lg+) to prevent mobile flash
    expect($html)->toContain("window.matchMedia('(min-width: 1024px)').matches");
});

test('app switcher dropdown lists enabled modules', function (): void {
    shellGrantModuleAccess('project-management', 'projects.view', $this->tenant, $this->user);

    $response = $this->actingAs($this->user)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->get('/dashboard');

    $response->assertSuccessful()
        ->assertSee('Workspace Apps')
        ->assertSee('Project Management');
});

// --- Helpers ---

function shellGrantModuleAccess(string $slug, string $permissionName, Tenant $tenant, User $user): void
{
    $permission = Permission::firstOrCreate([
        'name' => $permissionName,
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
        'category' => explode('.', $permissionName)[0],
    ]);

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo($permission);

    app(ModuleManager::class)->enableForTenant($slug, $tenant);
}
