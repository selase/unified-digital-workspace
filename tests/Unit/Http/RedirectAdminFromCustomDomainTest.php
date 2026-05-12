<?php

declare(strict_types=1);

use App\Http\Middleware\RedirectAdminFromCustomDomain;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * The middleware decides whether to redirect by matching the request host
 * against the active tenant's verified custom_domain and then inspecting
 * the resolved route name. We assert each branch by hand-building a Request
 * with the route attribute populated — full app boot would drag in tenant
 * resolution, session, CSRF, etc.
 */
function runMiddleware(Tenant $tenant, string $host, ?string $routeName): Response
{
    $context = new TenantContext;
    $context->setTenant($tenant);
    $middleware = new RedirectAdminFromCustomDomain($context);

    $request = Request::create("https://{$host}/login");
    $request->headers->set('HOST', $host);

    if ($routeName !== null) {
        $route = (new Route(['GET'], '/login', []))->name($routeName);
        $request->setRouteResolver(fn () => $route);
    }

    return $middleware->handle(
        $request,
        fn (Request $req) => response('OK', 200)
    );
}

function makeTenant(string $domain, string $status = 'active'): Tenant
{
    $tenant = new Tenant;
    $tenant->id = '019cfefd-0423-73ff-8970-c54ebf4c2b67';
    $tenant->slug = 'thyroid-ghana-foundation';
    $tenant->custom_domain = $domain;
    $tenant->custom_domain_status = $status;

    return $tenant;
}

beforeEach(function (): void {
    config(['session.domain' => '.udworkspace.com']);
});

test('admin route on a verified custom domain redirects to tenant subdomain', function (): void {
    $tenant = makeTenant('thyroidghanafoundation.org');

    $response = runMiddleware($tenant, 'thyroidghanafoundation.org', 'login');

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toBe(
        'https://thyroid-ghana-foundation.udworkspace.com/login'
    );
});

test('cms public route on a custom domain is passed through', function (): void {
    $tenant = makeTenant('thyroidghanafoundation.org');

    $response = runMiddleware($tenant, 'thyroidghanafoundation.org', 'cms-core.website.home');

    expect($response->getStatusCode())->toBe(200);
    expect((string) $response->getContent())->toBe('OK');
});

test('site-icon route on a custom domain is passed through', function (): void {
    $tenant = makeTenant('thyroidghanafoundation.org');

    $response = runMiddleware($tenant, 'thyroidghanafoundation.org', 'site-icon.favicon');

    expect($response->getStatusCode())->toBe(200);
});

test('admin route on the platform host is passed through', function (): void {
    $tenant = makeTenant('thyroidghanafoundation.org');

    $response = runMiddleware($tenant, 'udworkspace.com', 'login');

    expect($response->getStatusCode())->toBe(200);
});

test('pending custom domain does not trigger a redirect', function (): void {
    $tenant = makeTenant('thyroidghanafoundation.org', status: 'pending');

    $response = runMiddleware($tenant, 'thyroidghanafoundation.org', 'login');

    expect($response->getStatusCode())->toBe(200);
});

test('host comparison is case-insensitive', function (): void {
    $tenant = makeTenant('thyroidghanafoundation.org');

    $response = runMiddleware($tenant, 'THYROIDGhanaFoundation.ORG', 'login');

    expect($response->getStatusCode())->toBe(302);
});
