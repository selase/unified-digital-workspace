<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

final class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'webhooks/*',
        'webhooks/merchant/*',
        // Public enquiry forms. Custom domains cannot hold the platform session
        // cookie, so these are protected by a honeypot, a signed render-time
        // token and rate limiting instead (see EnquiryToken).
        'enquiries',
        'site/enquiries',
    ];
}
