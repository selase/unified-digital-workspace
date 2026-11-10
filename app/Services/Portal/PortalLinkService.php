<?php

declare(strict_types=1);

namespace App\Services\Portal;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Sign-in for people who are not staff (volunteers, members). Nobody has a password: they ask for a link, it is
 * emailed to the address on file, and opening it once starts a short session limited to that one record.
 */
final class PortalLinkService
{
    public const LINK_MINUTES = 30;

    public const SESSION_HOURS = 8;

    /** A one-time sign-in link. Built on the configured app URL so the signature matches wherever the email is opened. */
    public function link(string $routeName, array $parameters, int $minutes = self::LINK_MINUTES): string
    {
        $generator = clone app('url');
        $root = mb_rtrim((string) config('app.url'), '/');
        $generator->forceRootUrl($root);
        $generator->forceScheme(parse_url($root, PHP_URL_SCHEME) ?: 'https');

        return $generator->temporarySignedRoute($routeName, now()->addMinutes($minutes), $parameters + ['nonce' => Str::random(32)]);
    }

    /** At most five link requests a minute per visitor, so the form cannot be used to flood someone's inbox. */
    public function allowRequest(Request $request): bool
    {
        $key = 'portal-link-request:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return false;
        }
        RateLimiter::hit($key, 60);

        return true;
    }

    /** True the first time a link's nonce is seen. Opening the same link again (or forwarding it after use) fails. */
    public function consume(Request $request): bool
    {
        $nonce = (string) $request->query('nonce', '');

        return $nonce !== '' && Cache::add('portal-link:'.$nonce, 1, now()->addMinutes(self::LINK_MINUTES + 5));
    }

    public function establish(Request $request, string $kind, string $tenantId, int $personId): void
    {
        $request->session()->regenerate();
        $request->session()->put($this->key($kind, $tenantId), ['id' => $personId, 'until' => now()->addHours(self::SESSION_HOURS)->timestamp]);
    }

    /** The signed-in record id for this kind of portal and organisation, or null when there is none or it has expired. */
    public function current(Request $request, string $kind, string $tenantId): ?int
    {
        $session = $request->session()->get($this->key($kind, $tenantId));
        if (! is_array($session) || ($session['until'] ?? 0) < now()->timestamp) {
            $request->session()->forget($this->key($kind, $tenantId));

            return null;
        }

        return (int) $session['id'];
    }

    public function forget(Request $request, string $kind, string $tenantId): void
    {
        $request->session()->forget($this->key($kind, $tenantId));
    }

    private function key(string $kind, string $tenantId): string
    {
        return "portal.{$kind}.{$tenantId}";
    }
}
