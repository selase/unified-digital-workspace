<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class MeterRequestUsage
{
    public function __construct(
        private readonly TenantContext $context
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        // Skip metering on 5xx — the request already aborted any in-flight
        // transaction, so inserting a usage row here would just raise a
        // secondary "in failed sql transaction" and mask the real exception.
        if ($response->getStatusCode() >= 500) {
            return $response;
        }

        $tenant = $this->context->getTenant();

        if ($tenant) {
            $duration = (int) ((microtime(true) - $start) * 1000);
            $statusCode = $response->getStatusCode();
            $statusBucket = (string) (floor($statusCode / 100) * 100).'xx';
            $routeName = ($request->route() && $request->route()->getName())
                ? $request->route()->getName()
                : ($request->method().' '.$request->path());

            // We'll dispatch a job or use a service to record this
            // For now, let's call a service directly (we can queue it later for performance)
            app(\App\Services\Tenancy\UsageService::class)->recordRequest(
                $tenant,
                $routeName,
                $statusCode,
                $statusBucket,
                $duration
            );
        }

        return $response;
    }
}
