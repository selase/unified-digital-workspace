<?php

declare(strict_types=1);

use App\Enum\TenantStatusEnum;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    refreshTenantDatabases();

    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    $this->artisan('db:seed', ['--class' => 'PermissionsSeeder']);

    // Reset Team ID to ensure we find the global roles for assignment
    setPermissionsTeamId(null);
    Role::findByName('Org Superadmin')->givePermissionTo(['read user', 'access dashboard']);

    // Verify seeding
    if (Role::count() === 0) {
        throw new Exception('Manual seeding failed: No roles found.');
    }

    $this->tenant = Tenant::create([
        'name' => 'Hospital A',
        'slug' => 'hospital-a',
        'status' => TenantStatusEnum::ACTIVE,
    ]);

    $this->orgSuperadmin = User::factory()->create(['email' => 'admin@hospital-a.test']);
    $this->orgSuperadmin->tenants()->attach($this->tenant->id);

    setPermissionsTeamId($this->tenant->id);
    $this->orgSuperadmin->assignRole('Org Superadmin');
});

test('org superadmin can create a custom role scoped to their tenant', function () {
    $this->actingAs($this->orgSuperadmin);

    $roleName = 'Visiting Professor';

    $response = $this->postJson(route('tenant.roles.store', ['subdomain' => $this->tenant->slug]), [
        'name' => $roleName,
        'permissions' => ['read user', 'access dashboard'],
    ]);

    $response->assertStatus(200);

    // Verify role exists in landlord DB with tenant_id
    $this->assertDatabaseHas('roles', [
        'name' => $roleName,
        'tenant_id' => $this->tenant->id,
    ], 'landlord');

    $role = Role::where('name', $roleName)->where('tenant_id', $this->tenant->id)->first();
    expect($role->hasPermissionTo('read user'))->toBeTrue();
    expect($role->hasPermissionTo('access dashboard'))->toBeTrue();
});

test('org superadmin cannot assign non-whitelisted permissions', function () {
    $this->actingAs($this->orgSuperadmin);

    $response = $this->postJson(route('tenant.roles.store', ['subdomain' => $this->tenant->slug]), [
        'name' => 'Hacker Role',
        'permissions' => ['delete tenant', 'create setting'],
    ]);

    $response->assertStatus(422); // Validation error
    $response->assertJsonValidationErrors(['permissions.0', 'permissions.1']);
});

test('tenant a cannot see roles from tenant b', function () {
    // Create role for Tenant B
    $tenantB = Tenant::create(['name' => 'Hospital B', 'slug' => 'hospital-b', 'status' => TenantStatusEnum::ACTIVE]);
    Role::create(['name' => 'Secret Role', 'tenant_id' => $tenantB->id, 'guard_name' => 'web']);

    $this->actingAs($this->orgSuperadmin);

    $response = $this->getJson(route('tenant.roles.index', ['subdomain' => $this->tenant->slug]));

    $response->assertStatus(200);
    $response->assertDontSee('Secret Role');
});

test('org superadmin cannot delete system roles', function () {
    $this->actingAs($this->orgSuperadmin);

    // Reset team ID to find global roles
    setPermissionsTeamId(null);
    $systemRole = Role::where('name', 'Org Admin')->whereNull('tenant_id')->first();

    // Restore team ID for the request
    setPermissionsTeamId($this->tenant->id);

    if (! $systemRole) {
        $allRoles = Role::all()->pluck('name', 'tenant_id');
        throw new Exception("System role 'Org Admin' not found. Available roles: ".$allRoles);
    }

    $response = $this->deleteJson(route('tenant.roles.destroy', ['subdomain' => $this->tenant->slug, 'role' => $systemRole->id]));

    $response->assertStatus(403);
});

test('global superadmin can create a role for a specific tenant', function () {
    $globalAdmin = User::factory()->create();
    setPermissionsTeamId(null);
    $globalAdmin->assignRole('Superadmin');

    $this->actingAs($globalAdmin);

    $roleName = 'Global Created Role';

    // Global admin uses the landlord route but specifies tenant_id
    // We send as JSON to get 200
    $response = $this->postJson(route('roles.store'), [
        'name' => $roleName,
        'tenant_id' => $this->tenant->id,
        'permissions' => ['read user'],
    ]);

    $response->assertStatus(200);

    $this->assertDatabaseHas('roles', [
        'name' => $roleName,
        'tenant_id' => $this->tenant->id,
    ], 'landlord');
});
