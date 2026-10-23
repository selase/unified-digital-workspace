<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Services\Tenancy\TenantAdminRedirect;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;

it('keeps host only redirects on the authenticated origin', function (?string $intended, string $expected): void {
    $request = Request::create('https://preview.laravel.cloud/login');
    $session = new Store('isolated', new ArraySessionHandler(120));
    $session->start();
    $request->setLaravelSession($session);
    $session->put('url.intended', $intended);
    $routes = new RouteCollection;
    $routes->add((new Route(['GET'], 'workspace/{subdomain}/dashboard', []))->name('tenant.dashboard'));
    $redirector = new Redirector(new UrlGenerator($routes, $request));
    $redirector->setSession($session);
    $tenant = new Tenant;
    $tenant->slug = 'acme';
    $service = new TenantAdminRedirect(new Repository(['session' => ['domain' => null]]), $redirector);

    expect($service->toDashboard($request, $tenant)->getTargetUrl())->toBe($expected)
        ->and($session->has('url.intended'))->toBeFalse();
})->with([
    [null, 'https://preview.laravel.cloud/workspace/acme/dashboard'],
    ['/settings?tab=profile', 'https://preview.laravel.cloud/settings?tab=profile'],
    ['https://preview.laravel.cloud/settings', 'https://preview.laravel.cloud/settings'],
    ['https://other.example.test/dashboard', 'https://preview.laravel.cloud/workspace/acme/dashboard'],
    ['//other.example.test/dashboard', 'https://preview.laravel.cloud/workspace/acme/dashboard'],
    ['https://preview.laravel.cloud.evil.test/', 'https://preview.laravel.cloud/workspace/acme/dashboard'],
    ['/\\other.example.test/', 'https://preview.laravel.cloud/workspace/acme/dashboard'],
    ["/settings\nX-Forged: yes", 'https://preview.laravel.cloud/workspace/acme/dashboard'],
]);

it('uses the tenant routing base and canonical scheme and port for shared sessions', function (): void {
    $request = Request::create('https://login.example.test/login');
    $session = new Store('shared', new ArraySessionHandler(120));
    $session->start();
    $request->setLaravelSession($session);
    $routes = new RouteCollection;
    $routes->add((new Route(['GET'], 'workspace/{subdomain}/dashboard', []))->name('tenant.dashboard'));
    $redirector = new Redirector(new UrlGenerator($routes, $request));
    $redirector->setSession($session);
    $tenant = new Tenant;
    $tenant->slug = 'acme';
    $service = new TenantAdminRedirect(new Repository([
        'app' => ['url' => 'https://login.example.test:8443', 'tenant_domain' => 'workspace.example.test'],
        'session' => ['domain' => '.workspace.example.test'],
    ]), $redirector);

    expect($service->toDashboard($request, $tenant)->getTargetUrl())->toBe('https://acme.workspace.example.test:8443/dashboard');
});

it('refuses a host bound tenant switch rather than silently retaining the old organisation', function (): void {
    $request = Request::create('https://old.workspace.example.test/tenants/change/new');
    $routes = new RouteCollection;
    $routes->add((new Route(['GET'], 'workspace/{subdomain}/dashboard', []))->name('tenant.dashboard'));
    $redirector = new Redirector(new UrlGenerator($routes, $request));
    $tenant = new Tenant;
    $tenant->slug = 'new';
    $service = new TenantAdminRedirect(new Repository([
        'app' => ['tenant_domain' => 'workspace.example.test'], 'session' => ['domain' => null],
    ]), $redirector);

    try {
        $service->assertSwitchOrigin($request, $tenant);
        test()->fail('The old tenant origin must not report a successful switch.');
    } catch (Symfony\Component\HttpKernel\Exception\HttpException $exception) {
        expect($exception->getStatusCode())->toBe(409);
    }
});
