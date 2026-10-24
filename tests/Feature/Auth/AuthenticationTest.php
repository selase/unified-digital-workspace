<?php

declare(strict_types=1);

use App\Models\User;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\Position;

beforeEach(function () {
    refreshTenantDatabases();

    Location::shouldReceive('get')->andReturn(
        Position::make(['countryName' => 'Testland'])
    );
});

test('login screen can be rendered', function () {
    $tenant = setActiveTenantForTest();

    $this->withSession(['active_tenant_id' => $tenant->id])
        ->get('/login')
        ->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    $response = $this->withSession(['active_tenant_id' => $tenant->id])
        ->withoutMiddleware(App\Http\Middleware\VerifyCsrfToken::class)
        ->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

    $expectedUrl = 'http://'.$tenant->slug.'.unified-digital-workspace.test/dashboard';
    $response->assertRedirect($expectedUrl);
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    $this->withSession(['active_tenant_id' => $tenant->id])
        ->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

    $this->assertGuest();
});

test('host only login stays on its origin and opens the tenant dashboard', function (): void {
    useHostOnlyTenantRoutesForTest();
    $user = User::factory()->create();
    $tenant = App\Models\Tenant::factory()->create(['slug' => 'cloud-login', 'isolation_mode' => 'shared']);
    $tenant->users()->attach($user->id);
    setPermissionsTeamId($tenant->id);
    grantOrganisationAdminAccess($user);

    $response = $this->withSession(['url.intended' => 'https://cloud-login.workspace.example.test/dashboard'])
        ->post('https://preview.laravel.cloud/login', ['email' => $user->email, 'password' => 'password']);

    $response->assertRedirect('https://preview.laravel.cloud/workspace/cloud-login/dashboard');
    $this->assertAuthenticatedAs($user);
    $response->assertSessionHas('active_tenant_id', $tenant->id);
    $dashboard = $this->get('https://preview.laravel.cloud/workspace/cloud-login/dashboard')->assertOk()->assertViewIs('tenant.dashboard');
    $dashboard->assertSee('https://preview.laravel.cloud/workspace/cloud-login/users', false);
    $dashboard->assertDontSee('cloud-login.workspace.example.test/users', false);
    $this->get('https://preview.laravel.cloud/workspace/cloud-login/users')->assertOk();
    $this->get('https://preview.laravel.cloud/workspace/cloud-login/roles')->assertOk();
    $this->get('https://preview.laravel.cloud/workspace/cloud-login/api-keys')->assertOk();
});

test('shared cookie login uses the configured tenant routing domain', function (): void {
    config(['session.domain' => '.workspace.example.test', 'app.tenant_domain' => 'workspace.example.test']);
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    $this->withSession(['active_tenant_id' => $tenant->id])->post('/login', [
        'email' => $user->email, 'password' => 'password',
    ])->assertRedirect('http://'.$tenant->slug.'.workspace.example.test/dashboard');
});

test('login retains inherited access to a descendant tenant', function (): void {
    $user = User::factory()->create();
    $parent = App\Models\Tenant::factory()->create(['isolation_mode' => 'shared']);
    $child = App\Models\Tenant::factory()->create(['slug' => 'descendant', 'parent_id' => $parent->id, 'isolation_mode' => 'shared']);
    $parent->users()->attach($user->id);

    $this->post('https://descendant.unified-digital-workspace.test/login', [
        'email' => $user->email, 'password' => 'password',
    ])->assertRedirect('http://descendant.unified-digital-workspace.test/dashboard')
        ->assertSessionHas('active_tenant_id', $child->id);
});
