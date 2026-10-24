<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Temporary pre-cutover guard for private Cloud rehearsals. Active only while
 * REHEARSAL_GUARD_PASSWORD is set; never merge into the cutover branch.
 */
final class RehearsalBasicGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $password = (string) config('app.rehearsal_guard_password', '');

        if ($password === '') {
            return $next($request);
        }

        if ($request->path() === 'up') {
            return response('', 204);
        }

        if ($request->getUser() === 'udw' && hash_equals($password, (string) $request->getPassword())) {
            return $next($request);
        }

        return response('Unauthorized', 401, ['WWW-Authenticate' => 'Basic realm="UDW rehearsal"']);
    }
}
