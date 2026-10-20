<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->originalTeamId = getPermissionsTeamId();
    $this->tenant = setActiveTenantForTest();
    $this->manager = app(ModuleManager::class);
    $this->modulePermissions = $this->manager->find('forums')['permissions'];
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    setPermissionsTeamId($this->originalTeamId);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function moduleAdminRole(string $name, ?string $tenantId): Role
{
    return Role::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
        'tenant_id' => $tenantId,
    ]);
}

test('module enable adds permissions only to the target tenant administrator roles', function (): void {
    $otherTenant = Tenant::factory()->create();
    $roles = [];
    $untouchedRoles = [];
    $baseline = Permission::query()->firstOrCreate([
        'name' => 'core.dashboard.access', 'guard_name' => 'web',
    ], ['category' => 'core']);

    foreach (['Org Admin', 'Org Superadmin'] as $name) {
        $roles[] = $role = moduleAdminRole($name, $this->tenant->id);
        $role->givePermissionTo($baseline);
        $untouchedRoles[] = moduleAdminRole($name, $otherTenant->id);
        $untouchedRoles[] = moduleAdminRole($name, null);
    }
    $untouchedRoles[] = moduleAdminRole('Org Member', $this->tenant->id);
    $untouchedRoles[] = moduleAdminRole('Superadmin', null);

    setPermissionsTeamId($otherTenant->id);
    app(TenantContext::class)->setTenant($otherTenant);
    $this->manager->enableForTenant('forums', $this->tenant);

    expect(getPermissionsTeamId())->toBe($otherTenant->id);
    foreach ($roles as $role) {
        expect($role->fresh()->permissions->pluck('name')->all())
            ->toContain('core.dashboard.access', ...$this->modulePermissions);
    }
    foreach ($untouchedRoles as $role) {
        expect($role->fresh()->permissions->pluck('name')->intersect($this->modulePermissions))->toBeEmpty();
    }
});

test('administrators assigned after enable inherit module access without direct grants', function (string $roleName): void {
    $this->seed(RoleSeeder::class);
    $role = Role::query()->whereNull('tenant_id')->where('name', $roleName)->firstOrFail();
    $baseline = Permission::query()->firstOrCreate([
        'name' => 'core.dashboard.access', 'guard_name' => 'web',
    ], ['category' => 'core']);
    $role->givePermissionTo($baseline);
    $this->manager->enableForTenant('forums', $this->tenant);

    Mail::fake();
    $creator = User::factory()->create();
    $this->tenant->users()->attach($creator->id);
    $permission = Permission::query()->firstOrCreate([
        'name' => 'core.users.create', 'guard_name' => 'web',
    ], ['category' => 'core']);
    $creator->givePermissionTo($permission);

    $this->actingAs($creator)->postJson(route('tenant.users.store', ['subdomain' => $this->tenant->slug]), [
        'first_name' => 'Later',
        'last_name' => 'Administrator',
        'email' => 'later-admin@example.com',
        'phone_no' => '+233201234567',
        'roles' => [$role->id],
        'status' => 'active',
    ])->assertSuccessful();
    $user = User::query()->where('email', 'later-admin@example.com')->firstOrFail();

    expect($user->fresh()->roles->sole()->tenant_id)->toBe($this->tenant->id);
    expect($user->fresh()->hasPermissionTo('core.dashboard.access'))->toBeTrue();
    expect($role->fresh()->permissions->pluck('name')->intersect($this->modulePermissions))->toBeEmpty();
    expect($user->fresh()->getDirectPermissions())->toBeEmpty();
    foreach ($this->modulePermissions as $permission) {
        expect($user->fresh()->hasPermissionTo($permission))->toBeTrue();
    }
    $this->actingAs($user)->get(route('forums.hub'))->assertSuccessful();
})->with(['Org Admin', 'Org Superadmin']);

test('module enable preserves direct grants for existing administrators including global roles', function (string $roleName, bool $global): void {
    $role = moduleAdminRole($roleName, $global ? null : $this->tenant->id);
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $this->tenant->users()->attach([$admin->id, $member->id]);
    $admin->assignRole($role);

    $this->manager->enableForTenant('forums', $this->tenant);

    expect($admin->fresh()->getDirectPermissions()->pluck('name')->all())->toContain(...$this->modulePermissions);
    expect($member->fresh()->getDirectPermissions())->toBeEmpty();
    if ($global) {
        expect($role->fresh()->permissions->pluck('name')->intersect($this->modulePermissions))->toBeEmpty();
    }
})->with([
    ['Org Admin', false], ['Org Superadmin', false],
    ['Org Admin', true], ['Org Superadmin', true],
]);

test('reenabling an already enabled module backfills roles and direct grants idempotently', function (): void {
    $this->manager->enableForTenant('forums', $this->tenant);
    $roles = collect(['Org Admin', 'Org Superadmin'])->map(
        fn (string $name): Role => moduleAdminRole($name, $this->tenant->id)
    );
    foreach ($roles as $role) {
        $role->revokePermissionTo($this->modulePermissions);
    }
    $admin = User::factory()->create();
    $this->tenant->users()->attach($admin->id);
    $admin->assignRole($roles->first());

    $first = $this->manager->enableForTenant('forums', $this->tenant);
    $second = $this->manager->enableForTenant('forums', $this->tenant);

    expect($second->id)->toBe($first->id);
    foreach ($roles as $role) {
        expect($role->fresh()->permissions->pluck('name')->all())->toContain(...$this->modulePermissions);
        expect($role->fresh()->permissions()->whereIn('name', $this->modulePermissions)->count())->toBe(count($this->modulePermissions));
    }
    expect($admin->fresh()->getDirectPermissions()->pluck('name')->all())->toContain(...$this->modulePermissions);
});

test('module enable creates isolated administrator copies from global seeded roles', function (): void {
    $this->seed(RoleSeeder::class);
    $baseline = Permission::query()->firstOrCreate([
        'name' => 'core.dashboard.access', 'guard_name' => 'web',
    ], ['category' => 'core']);
    $globalRoles = Role::query()->whereNull('tenant_id')->whereIn('name', ['Org Admin', 'Org Superadmin'])->get();
    foreach ($globalRoles as $globalRole) {
        $globalRole->givePermissionTo($baseline);
    }
    $otherTenant = Tenant::factory()->create();
    $this->manager->enableForTenant('forums', $this->tenant);
    $this->manager->enableForTenant('forms', $otherTenant);
    $this->manager->enableForTenant('forums', $this->tenant);

    foreach ($globalRoles as $globalRole) {
        $copy = Role::query()->where('tenant_id', $this->tenant->id)->where('name', $globalRole->name)->sole();
        expect($copy->slug)->toBe($globalRole->slug);
        expect($copy->permissions->pluck('name')->all())->toContain('core.dashboard.access', ...$this->modulePermissions);
        $otherCopy = Role::query()->where('tenant_id', $otherTenant->id)->where('name', $globalRole->name)->sole();
        expect($otherCopy->permissions->pluck('name')->intersect($this->modulePermissions))->toBeEmpty();
        expect($globalRole->fresh()->permissions->pluck('name')->all())->toBe(['core.dashboard.access']);
    }
});

test('user role choices and edit selection prefer tenant copies over global admin roles', function (): void {
    $this->seed(RoleSeeder::class);
    $globalRole = Role::query()->whereNull('tenant_id')->where('name', 'Org Admin')->sole();
    $admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($admin->id);
    $admin->assignRole($globalRole);
    foreach (['core.users.read', 'core.users.update'] as $name) {
        $permission = Permission::query()->firstOrCreate([
            'name' => $name, 'guard_name' => 'web',
        ], ['category' => 'core']);
        $admin->givePermissionTo($permission);
    }
    $this->manager->enableForTenant('forums', $this->tenant);
    $copy = Role::query()->where('tenant_id', $this->tenant->id)->where('name', 'Org Admin')->sole();
    $foreignRole = moduleAdminRole('Org Admin', Tenant::factory()->create()->id);

    $this->actingAs($admin)->get(route('tenant.users.index', ['subdomain' => $this->tenant->slug]))
        ->assertSuccessful()
        ->assertViewHas('roles', function ($roles) use ($copy, $globalRole, $foreignRole): bool {
            return $roles->contains('id', $copy->id)
                && ! $roles->contains('id', $globalRole->id)
                && ! $roles->contains('id', $foreignRole->id)
                && $roles->where('name', 'Org Admin')->count() === 1;
        });
    $this->getJson(route('tenant.users.edit', ['subdomain' => $this->tenant->slug, 'user' => $admin->uuid]))
        ->assertSuccessful()
        ->assertJsonPath('roles', [$copy->id]);

    $this->putJson(route('tenant.users.update', ['subdomain' => $this->tenant->slug, 'user' => $admin->uuid]), [
        'first_name' => $admin->first_name,
        'last_name' => $admin->last_name,
        'email' => $admin->email,
        'phone_no' => '+233201234567',
        'status' => 'active',
        'roles' => [$globalRole->id, $copy->id],
    ])->assertSuccessful();
    expect($admin->fresh()->roles->sole()->id)->toBe($copy->id);
    expect($admin->fresh()->hasPermissionTo('forums.view'))->toBeTrue();
});

test('role shadow resolution keeps guard and tenant boundaries and global fallback', function (): void {
    $globalAdmin = moduleAdminRole('Org Admin', null);
    $globalMember = moduleAdminRole('Org Member', null);
    $globalSuperadmin = moduleAdminRole('Superadmin', null);
    $apiAdmin = Role::query()->create([
        'tenant_id' => $this->tenant->id, 'name' => 'Org Admin', 'guard_name' => 'api',
    ]);
    $foreignAdmin = moduleAdminRole('Org Admin', Tenant::factory()->create()->id);
    $controller = app(App\Http\Controllers\Tenant\UserController::class);
    $resolve = new ReflectionMethod($controller, 'assignableRoles');

    $roles = $resolve->invoke($controller, [$globalAdmin->id, $globalMember->id], $this->tenant);
    expect($roles->modelKeys())->toContain($globalAdmin->id, $globalMember->id);
    expect($roles->modelKeys())->not->toContain($apiAdmin->id, $foreignAdmin->id);

    foreach ([$foreignAdmin->id => 404, $globalSuperadmin->id => 403] as $id => $status) {
        try {
            $resolve->invoke($controller, [$id], $this->tenant);
            $this->fail('An unassignable role was accepted.');
        } catch (Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            expect($exception->getStatusCode())->toBe($status);
        }
    }
});
