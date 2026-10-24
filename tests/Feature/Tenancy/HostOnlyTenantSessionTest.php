<?php

declare(strict_types=1);

use App\Http\Middleware\RedirectAdminFromCustomDomain;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

it('resolves tenant subdomains independently of the cookie domain', function (): void {
    config(['session.domain' => null, 'app.tenant_domain' => 'workspace.example.test']);
    $tenant = Tenant::factory()->create(['slug' => 'host-only', 'isolation_mode' => 'shared']);

    $resolved = app(TenantResolver::class)->resolve(Request::create('https://host-only.workspace.example.test/'));

    expect($resolved?->id)->toBe($tenant->id);
});

it('redirects custom domain administrators using the tenant domain with host only cookies', function (): void {
    config(['session.domain' => null, 'app.tenant_domain' => 'workspace.example.test']);
    $tenant = new Tenant;
    $tenant->id = '019cfefd-0423-73ff-8970-c54ebf4c2b67';
    $tenant->slug = 'host-only';
    $tenant->custom_domain = 'customer.example.test';
    $tenant->custom_domain_status = 'active';
    $context = new TenantContext;
    $context->setTenant($tenant);
    $request = Request::create('https://customer.example.test/login?next=dashboard');
    $request->setRouteResolver(fn (): Route => (new Route(['GET'], '/login', []))->name('login'));

    $response = (new RedirectAdminFromCustomDomain($context))->handle($request, fn () => response('unexpected'));

    expect($response->headers->get('Location'))->toBe('https://host-only.workspace.example.test/login?next=dashboard');
});

it('issues secure host only session cookies and preserves session values', function (): void {
    config(['session.domain' => null, 'session.secure' => true, 'session.driver' => 'array']);
    Illuminate\Support\Facades\Route::middleware('web')->get('/__cloud-session-probe', function (Request $request) {
        $prior = $request->session()->get('cloud_probe');
        $request->session()->put('cloud_probe', 'retained');

        return response()->json(['prior' => $prior]);
    });

    $response = $this->get('https://preview.laravel.cloud/__cloud-session-probe');
    $response->assertOk();
    $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->getDomain())->toBeNull()
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax');
    $this->get('https://preview.laravel.cloud/__cloud-session-probe')->assertJson(['prior' => 'retained']);
});

it('registers tenant HTTP routes using the routing base with host only cookies', function (): void {
    useHostOnlyTenantRoutesForTest();
    $tenant = Tenant::factory()->create(['slug' => 'cloud-routes', 'isolation_mode' => 'shared']);
    $user = App\Models\User::factory()->create();
    $tenant->users()->attach($user->id);

    $this->actingAs($user)->get('https://cloud-routes.workspace.example.test/dashboard')
        ->assertOk()->assertViewIs('tenant.dashboard');

    expect(app(TenantContext::class)->getTenant()?->id)->toBe($tenant->id);
});

it('legacy tenant switching updates the active session and stays on the platform origin', function (): void {
    useHostOnlyTenantRoutesForTest();
    $user = App\Models\User::factory()->create();
    $tenant = Tenant::factory()->create(['slug' => 'switch-target', 'isolation_mode' => 'shared']);
    $tenant->users()->attach($user->id);

    $this->actingAs($user)->get('https://preview.laravel.cloud/tenants/change/'.$tenant->id)
        ->assertRedirect('https://preview.laravel.cloud/workspace/switch-target/dashboard')
        ->assertSessionHas('active_tenant_id', $tenant->id);
    $this->get('https://preview.laravel.cloud/workspace/switch-target/dashboard')->assertOk()->assertViewIs('tenant.dashboard');
});

it('rejects a workspace path for a different tenant on a tenant bound hostname', function (): void {
    useHostOnlyTenantRoutesForTest();
    $user = App\Models\User::factory()->create();
    $old = Tenant::factory()->create(['slug' => 'old', 'isolation_mode' => 'shared']);
    $target = Tenant::factory()->create(['slug' => 'next', 'isolation_mode' => 'shared']);
    $old->users()->attach($user->id);
    $target->users()->attach($user->id);

    $this->actingAs($user)->get('https://old.workspace.example.test/workspace/next/dashboard')->assertNotFound();
});

it('retains an authorised workspace path selection for subsequent global module routes', function (): void {
    useHostOnlyTenantRoutesForTest();
    $user = App\Models\User::factory()->create();
    $old = Tenant::factory()->create(['slug' => 'previous', 'isolation_mode' => 'shared']);
    $target = Tenant::factory()->create(['slug' => 'chosen', 'isolation_mode' => 'shared']);
    $old->users()->attach($user->id);
    $target->users()->attach($user->id);

    $this->actingAs($user)->withSession(['active_tenant_id' => $old->id])
        ->get('https://preview.laravel.cloud/workspace/chosen/dashboard')->assertOk()
        ->assertSessionHas('active_tenant_id', $target->id);
    $this->get('https://preview.laravel.cloud/dashboard')->assertOk()->assertViewIs('tenant.dashboard');
    expect(app(TenantContext::class)->getTenant()?->id)->toBe($target->id);
});

it('denies another tenants workspace path before changing the active session', function (): void {
    useHostOnlyTenantRoutesForTest();
    $user = App\Models\User::factory()->create();
    $own = Tenant::factory()->create(['slug' => 'own', 'isolation_mode' => 'shared']);
    Tenant::factory()->create(['slug' => 'other', 'isolation_mode' => 'shared']);
    $own->users()->attach($user->id);

    $this->actingAs($user)->withSession(['active_tenant_id' => $own->id])
        ->getJson('https://preview.laravel.cloud/workspace/other/dashboard')->assertForbidden()
        ->assertSessionHas('active_tenant_id', $own->id);
});
