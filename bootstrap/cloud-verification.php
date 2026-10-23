<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
    return;
}

(static function (): void {
    require_once dirname(__DIR__).'/vendor/autoload.php';

    $configuration = require dirname(__DIR__).'/config/cloud-verification.php';
    if (! $configuration['enabled']) {
        return;
    }

    $protectResponse = static function (): void {
        header('Cache-Control: no-store, private', true);
        header('X-Robots-Tag: noindex, nofollow', true);
    };
    $protectResponse();
    header_register_callback($protectResponse);

    if (in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)
        && parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === '/up') {
        http_response_code(204);
        exit;
    }

    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $token = $configuration['token'];
    if ($configuration['invalid'] || $token === ''
        || preg_match('/\ABearer ([A-Za-z0-9\-._~+\/=]+)\z/iD', $authorization, $matches) !== 1
        || ! hash_equals($token, $matches[1])) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Forbidden';
        exit;
    }
})();
