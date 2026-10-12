<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::get('/_headers-test', fn () => response('ok'));
    Route::get('/_headers-preset', fn () => response('ok', 200, ['Referrer-Policy' => 'no-referrer']));
});

it('adds baseline security headers, with HSTS over https', function (): void {
    $response = $this->get('https://example.test/_headers-test');

    expect($response->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->headers->get('Permissions-Policy'))->toBe('camera=(), microphone=(), geolocation=()')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('does not send HSTS over plain http', function (): void {
    $this->get('http://example.test/_headers-test')->assertHeaderMissing('Strict-Transport-Security');
});

it('does not override headers a route already set', function (): void {
    $this->get('https://example.test/_headers-preset')->assertHeader('Referrer-Policy', 'no-referrer');
});
