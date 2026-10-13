<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Support;

/**
 * Stateless anti-spam token for public forms.
 *
 * Custom domains cannot hold the platform session cookie, so a form cannot
 * rely on CSRF/session state. The token is a signed render timestamp: a bot
 * that posts instantly (or replays an old token) is rejected without any
 * server-side storage.
 */
final class EnquiryToken
{
    public static function issue(?int $issuedAt = null): string
    {
        $timestamp = (string) ($issuedAt ?? time());

        return $timestamp.'.'.self::signature($timestamp);
    }

    public static function isValid(string $token, int $minAgeSeconds = 3, int $maxAgeSeconds = 7200): bool
    {
        [$timestamp, $signature] = array_pad(explode('.', $token, 2), 2, '');

        if (! ctype_digit($timestamp) || ! hash_equals(self::signature($timestamp), $signature)) {
            return false;
        }

        $age = time() - (int) $timestamp;

        return $age >= $minAgeSeconds && $age <= $maxAgeSeconds;
    }

    private static function signature(string $timestamp): string
    {
        return hash_hmac('sha256', 'enquiry|'.$timestamp, (string) config('app.key'));
    }
}
