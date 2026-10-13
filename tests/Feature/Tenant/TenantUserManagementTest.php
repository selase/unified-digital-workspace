<?php

declare(strict_types=1);

use App\Enum\TenantStatusEnum;
use App\Mail\Users\SendAccountDetails;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    refreshTenantDatabases();

    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    $this->artisan('db:seed', ['--class' => 'PermissionsSeeder']);

    $this->tenant = Tenant::create([
        'name' => 'Hospital A',
        'slug' => 'hospital-a',
        'status' => TenantStatusEnum::ACTIVE,
    ]);

    $this->orgSuperadmin = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->orgSuperadmin->tenants()->attach($this->tenant->id);

    setPermissionsTeamId($this->tenant->id);
    $this->orgSuperadmin->assignRole('Org Superadmin');

    $this->editorRole = Role::create([
        'name' => 'CMS Editor',
        'tenant_id' => $this->tenant->id,
        'guard_name' => 'web',
    ]);

    $this->payload = [
        'first_name' => 'Ama',
        'last_name' => 'Mensah',
        'email' => 'ama@hospital-a.test',
        'phone_no' => '+233244123456',
        'status' => 'active',
    ];
});

test('org superadmin can add a user with the roles picked on the form', function () {
    Mail::fake();

    $this->actingAs($this->orgSuperadmin)
        ->postJson(route('tenant.users.store', ['subdomain' => $this->tenant->slug]), [
            ...$this->payload,
            'roles' => [$this->editorRole->id],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $user = User::query()->where('email', 'ama@hospital-a.test')->firstOrFail();

    setPermissionsTeamId($this->tenant->id);
    expect($user->tenant_id)->toBe($this->tenant->id)
        ->and($user->hasRole('CMS Editor'))->toBeTrue();

    Mail::assertQueued(SendAccountDetails::class);
});

test('org superadmin can change the roles of an existing user', function () {
    $member = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $member->tenants()->attach($this->tenant->id);
    $member->assignRole('Org Admin');

    $this->actingAs($this->orgSuperadmin)
        ->putJson(route('tenant.users.update', ['subdomain' => $this->tenant->slug, 'user' => $member->uuid]), [
            ...$this->payload,
            'email' => $member->email,
            'roles' => [$this->editorRole->id],
        ])
        ->assertOk();

    setPermissionsTeamId($this->tenant->id);
    expect($member->fresh()->getRoleNames()->all())->toBe(['CMS Editor']);

    $this->getJson(route('tenant.users.edit', ['subdomain' => $this->tenant->slug, 'user' => $member->uuid]))
        ->assertOk()
        ->assertJsonPath('roles', [$this->editorRole->id]);
});

test('a user cannot be given another tenant\'s role or the Superadmin role', function () {
    $otherTenant = Tenant::create(['name' => 'Hospital B', 'slug' => 'hospital-b', 'status' => TenantStatusEnum::ACTIVE]);
    $foreignRole = Role::create(['name' => 'Foreign Role', 'tenant_id' => $otherTenant->id, 'guard_name' => 'web']);

    setPermissionsTeamId(null);
    $superadmin = Role::findByName('Superadmin');
    setPermissionsTeamId($this->tenant->id);

    foreach ([$foreignRole->id, $superadmin->id] as $roleId) {
        $response = $this->actingAs($this->orgSuperadmin)
            ->postJson(route('tenant.users.store', ['subdomain' => $this->tenant->slug]), [
                ...$this->payload,
                'roles' => [$roleId],
            ]);

        expect($response->status())->toBeIn([403, 404, 422]);
    }

    expect(User::query()->where('email', 'ama@hospital-a.test')->exists())->toBeFalse();
});

test('a user with only content permissions is kept out of organisation, billing and developer pages', function () {
    setPermissionsTeamId($this->tenant->id);
    $this->editorRole->givePermissionTo(['core.dashboard.access']);

    $editor = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $editor->tenants()->attach($this->tenant->id);
    $editor->assignRole($this->editorRole);

    $routes = [
        'tenant.settings.index', 'tenant.users.index', 'tenant.roles.index', 'tenant.modules.index',
        'tenant.billing.index', 'tenant.pricing', 'tenant.settings.billing', 'tenant.settings.payments.index',
        'tenant.finance.index', 'tenant.api-keys.index', 'tenant.llm-usage.index', 'tenant.llm-config.index',
    ];

    foreach ($routes as $name) {
        $this->actingAs($editor)
            ->get(route($name, ['subdomain' => $this->tenant->slug]))
            ->assertForbidden();
    }

    $this->actingAs($editor)
        ->post(route('tenant.settings.billing.update', ['subdomain' => $this->tenant->slug]), ['billing_email' => 'x@example.com'])
        ->assertForbidden();

    $navigation = app(App\Services\Navigation\WorkspaceNavigation::class)
        ->forRequest(tap(request(), fn ($request) => $request->setUserResolver(fn () => $editor)));

    $visibleLinks = collect($navigation['sidebar']['tenant_groups'])->flatMap(fn (array $group) => $group['items'])->pluck('label')->all();

    expect($visibleLinks)->toBe([]);
});

test('an organisation admin still reaches billing settings and sees the organisation menu', function () {
    $this->actingAs($this->orgSuperadmin)
        ->get(route('tenant.settings.billing', ['subdomain' => $this->tenant->slug]))
        ->assertOk();

    $navigation = app(App\Services\Navigation\WorkspaceNavigation::class)
        ->forRequest(tap(request(), fn ($request) => $request->setUserResolver(fn () => $this->orgSuperadmin)));

    expect(collect($navigation['sidebar']['tenant_groups'])->pluck('label')->all())
        ->toContain('Organization', 'Finance', 'Developer');
});
