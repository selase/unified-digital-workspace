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
});

it('renders auth pages with metronic assets', function (): void {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('assets/metronic/css/styles.css');

    $this->get(route('password.request'))
        ->assertSuccessful()
        ->assertSee('assets/metronic/css/styles.css');
});
