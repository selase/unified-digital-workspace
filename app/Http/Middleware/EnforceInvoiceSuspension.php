<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Tenancy\BillingSuspensionService;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Takes a tenant's site temporarily offline once an invoice stays unpaid past
 * its suspension date. Signed-in members are sent to billing to pay; visitors
 * only see a neutral "temporarily unavailable" page.
 */
final class EnforceInvoiceSuspension
{
    private const ALLOWED_ROUTE_PATTERNS = [
        'billing.*', 'webhooks.*', 'login', 'logout', 'password.*', '2fa*', 'two-factor.*', 'verification.*',
    ];

    public function __construct(
        private readonly TenantContext $context,
        private readonly BillingSuspensionService $suspensions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->getTenant();
        if ($tenant === null || ! $this->suspensions->isSuspended($tenant)) {
            return $next($request);
        }

        $user = $request->user();
        if ($user instanceof User && $user->isGlobalSuperAdmin()) {
            return $next($request);
        }
        if ($request->routeIs(...self::ALLOWED_ROUTE_PATTERNS) || $request->is('livewire/*')) {
            return $next($request);
        }
        if ($user instanceof User) {
            return redirect()->route('billing.index')->with('warning', 'Your account has an unpaid invoice. Once it is settled, everything is switched back on straight away.');
        }

        return response()->view('errors.site-unavailable', [], 503)->header('Retry-After', '3600');
    }
}
