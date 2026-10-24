<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Providers\RouteServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;

final class TenantAdminRedirect
{
    public function __construct(private Repository $config, private Redirector $redirector) {}

    public function assertSwitchOrigin(Request $request, Tenant $tenant): void
    {
        $base = (string) $this->config->get('app.tenant_domain');
        if (! $this->config->get('session.domain') && $base !== ''
            && str_ends_with($request->getHost(), '.'.$base)) {
            if ($request->getHost() !== $tenant->slug.'.'.$base) {
                throw new \Symfony\Component\HttpKernel\Exception\HttpException(409, 'Use the platform host to switch organisations with host-only sessions.');
            }
        }
    }

    public function toDashboard(Request $request, Tenant $tenant): RedirectResponse
    {
        if ($this->config->get('session.domain')) {
            $scheme = parse_url((string) $this->config->get('app.url'), PHP_URL_SCHEME) ?: $request->getScheme();
            $port = parse_url((string) $this->config->get('app.url'), PHP_URL_PORT);
            $origin = $scheme.'://'.$tenant->slug.'.'.$this->config->get('app.tenant_domain').($port ? ':'.$port : '');

            return $this->redirector->intended($origin.RouteServiceProvider::HOME);
        }

        $origin = $request->getSchemeAndHttpHost();
        $intended = $request->session()->pull('url.intended');
        if (is_string($intended) && ! preg_match('/[\\\\\x00-\x20\x7f]/', $intended)) {
            if (str_starts_with($intended, '/') && ! str_starts_with($intended, '//')) {
                return $this->redirector->to($origin.$intended);
            }
            if (str_starts_with($intended, $origin.'/')) {
                return $this->redirector->to($intended);
            }
        }

        $path = $this->redirector->getUrlGenerator()->route('tenant.dashboard', ['subdomain' => $tenant->slug], false);

        return $this->redirector->to($origin.$path);
    }
}
