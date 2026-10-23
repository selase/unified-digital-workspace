<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('protects HTTP before Laravel or maintenance executes', function (string $flag, ?string $configuredToken, ?string $authorization, string $uri, string $method, int $status, bool $reachesApplication, string $suffix = '', string|false $cloudFlag = false, string|false $cloudToken = false): void {
    $root = sys_get_temp_dir().'/udw-guard-'.bin2hex(random_bytes(8));
    mkdir($root.'/public', 0700, true);
    mkdir($root.'/bootstrap', 0700);
    mkdir($root.'/config', 0700);
    mkdir($root.'/vendor', 0700);
    mkdir($root.'/storage/framework', 0700, true);
    $project = dirname(__DIR__, 2);
    copy($project.'/public/index.php', $root.'/public/index.php');
    foreach (['bootstrap/cloud-verification.php', 'config/cloud-verification.php'] as $path) {
        if (is_file($project.'/'.$path)) {
            copy($project.'/'.$path, $root.'/'.$path);
        }
    }
    file_put_contents($root.'/vendor/autoload.php', '<?php require '.var_export($project.'/vendor/autoload.php', true).';');
    file_put_contents($root.'/bootstrap/app.php', '<?php header("Cache-Control: public, max-age=3600"); header("X-Robots-Tag: index"); echo "APPLICATION"; exit;');
    file_put_contents($root.'/storage/framework/maintenance.php', '<?php header("Cache-Control: public, max-age=3600"); header("X-Robots-Tag: index"); echo "APPLICATION"; exit;');
    $token = bin2hex(random_bytes(24));
    $value = $configuredToken === null ? '' : $token;
    file_put_contents($root.'/.env', "CLOUD_VERIFICATION_ISOLATED=$flag\nCLOUD_VERIFICATION_TOKEN=\"$value\"\n".$suffix);
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $process = new Process([PHP_BINARY, '-S', $address, '-t', $root.'/public', $root.'/public/index.php'], $root, [
        'CLOUD_VERIFICATION_ISOLATED' => $cloudFlag,
        'CLOUD_VERIFICATION_TOKEN' => $cloudToken === '{token}' ? $token : $cloudToken,
    ]);
    $process->start();
    try {
        $process->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'Development Server'));
        $headers = $authorization === null ? '' : 'Authorization: '.str_replace('{token}', $token, $authorization)."\r\n";
        $context = stream_context_create(['http' => ['method' => $method, 'header' => $headers, 'ignore_errors' => true, 'timeout' => 5]]);
        $body = file_get_contents('http://'.$address.$uri, false, $context);
        $responseHeaders = http_get_last_response_headers();
        expect($responseHeaders[0])->toContain(' '.$status.' ');
        expect(str_contains((string) $body, 'APPLICATION'))->toBe($reachesApplication);
        expect((string) $body)->not->toContain($token);
        if ($flag !== 'false' && $cloudFlag !== 'false') {
            $responseHeaders = mb_strtolower(implode("\n", $responseHeaders));
            expect($responseHeaders)->toContain('cache-control: no-store, private', 'x-robots-tag: noindex, nofollow');
        }
    } finally {
        $process->stop();
        (new Illuminate\Filesystem\Filesystem)->deleteDirectory($root);
    }
})->with([
    'missing bearer' => ['true', 'set', null, '/', 'GET', 403, false],
    'wrong bearer' => ['true', 'set', 'Bearer wrong', '/', 'GET', 403, false],
    'basic rejected' => ['true', 'set', 'Basic {token}', '/', 'GET', 403, false],
    'query token rejected' => ['true', 'set', null, '/?token=wrong', 'GET', 403, false],
    'missing configured secret' => ['true', null, 'Bearer wrong', '/', 'GET', 403, false],
    'authorized' => ['true', 'set', 'Bearer {token}', '/', 'GET', 200, true],
    'case insensitive scheme' => ['true', 'set', 'bearer {token}', '/', 'GET', 200, true],
    'health' => ['true', null, null, '/up', 'GET', 204, false],
    'head health' => ['true', null, null, '/up', 'HEAD', 204, false],
    'health post denied' => ['true', 'set', null, '/up', 'POST', 403, false],
    'health subpath denied' => ['true', 'set', null, '/up/more', 'GET', 403, false],
    'malformed dotenv' => ['true', 'set', 'Bearer {token}', '/', 'GET', 403, false, 'BROKEN=unquoted space'],
    'unknown flag fails closed' => ['typo', 'set', null, '/', 'GET', 403, false],
    'cloud secret overrides file' => ['true', 'set', 'Bearer {token}', '/', 'GET', 403, false, '', 'true', 'different'],
    'cloud enables isolation' => ['false', 'set', null, '/', 'GET', 403, false, '', 'true'],
    'cloud disables isolation' => ['true', 'set', null, '/', 'GET', 200, true, '', 'false'],
    'cloud provides secret' => ['true', null, 'Bearer {token}', '/', 'GET', 200, true, '', 'true', '{token}'],
    'disabled' => ['false', null, null, '/', 'GET', 200, true],
]);

it('leaves CLI entry points unaffected even with isolated mode and no token', function (): void {
    $process = new Process([PHP_BINARY, '-r', 'require "bootstrap/cloud-verification.php"; echo "CLI";'], dirname(__DIR__, 2), [
        'CLOUD_VERIFICATION_ISOLATED' => 'true',
        'CLOUD_VERIFICATION_TOKEN' => '',
    ]);
    $process->mustRun();

    expect($process->getOutput())->toBe('CLI')->and($process->getErrorOutput())->toBe('');
});
