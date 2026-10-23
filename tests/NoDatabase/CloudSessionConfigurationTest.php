<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('keeps absent session cookie domains host only', function (?string $domain): void {
    $script = 'require "vendor/autoload.php"; new Illuminate\\Foundation\\Application(getcwd()); echo json_encode((require "config/session.php")["domain"]);';
    $process = new Process([PHP_BINARY, '-r', $script], dirname(__DIR__, 2), ['SESSION_DOMAIN' => $domain ?? false]);
    $process->mustRun();

    expect(json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR))->toBeNull();
})->with([null, '', 'null', '(null)']);

it('preserves a configured parent cookie domain', function (string $domain): void {
    $process = new Process([PHP_BINARY, '-r', 'require "vendor/autoload.php"; new Illuminate\\Foundation\\Application(getcwd()); echo json_encode((require "config/session.php")["domain"]);'], dirname(__DIR__, 2), ['SESSION_DOMAIN' => $domain]);
    $process->mustRun();

    expect(json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR))->toBe('.udworkspace.com');
})->with(['udworkspace.com', '.udworkspace.com']);

it('resolves the tenant base domain separately with compatible defaults', function (?string $tenantDomain, ?string $cookieDomain, string $expected): void {
    $script = 'require "vendor/autoload.php"; new Illuminate\\Foundation\\Application(getcwd()); echo json_encode((require "config/app.php")["tenant_domain"] ?? null);';
    $process = new Process([PHP_BINARY, '-r', $script], dirname(__DIR__, 2), [
        'TENANT_BASE_DOMAIN' => $tenantDomain ?? false,
        'SESSION_DOMAIN' => $cookieDomain ?? false,
        'APP_URL' => 'https://preview.laravel.cloud',
    ]);
    $process->mustRun();

    expect(json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR))->toBe($expected);
})->with([
    ['workspace.example.test', null, 'workspace.example.test'],
    [null, '.udworkspace.com', 'udworkspace.com'],
    [null, null, 'preview.laravel.cloud'],
]);

it('generates tenant public URLs from the independent routing base', function (): void {
    $script = 'require "vendor/autoload.php"; $app = new Illuminate\\Foundation\\Application(getcwd()); $app->instance("config", new Illuminate\\Config\\Repository(["app" => ["url" => "https://platform.example.test", "tenant_domain" => "workspace.example.test"]])); $tenant = new App\\Models\\Tenant; $tenant->slug = "acme"; echo $tenant->url("/about");';
    $process = new Process([PHP_BINARY, '-r', $script], dirname(__DIR__, 2));
    $process->mustRun();

    expect($process->getOutput())->toBe('https://acme.workspace.example.test/about');
});
