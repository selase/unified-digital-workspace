<?php

declare(strict_types=1);

use App\Services\Tenancy\LocalMediaIntegrityAudit;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\FilesystemManager;

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/udw-media-audit-'.bin2hex(random_bytes(8));
    mkdir($this->root);
    $container = new Container;
    $container->instance('config', new Repository(['filesystems' => ['disks' => ['audit' => ['driver' => 'local', 'root' => $this->root, 'throw' => true]]]]));
    $this->disk = (new FilesystemManager($container))->disk('audit');
    $this->audit = new LocalMediaIntegrityAudit;
});

afterEach(function (): void {
    (new Illuminate\Filesystem\Filesystem)->deleteDirectory($this->root);
});

it('streams fake local objects and reports independent size and checksum failures without writing', function (): void {
    $this->disk->put('original.bin', 'actual bytes');
    $record = ['tenant_id' => 'tenant-a', 'disk' => 'tenant', 'path' => 'original.bin', 'size_bytes' => 1, 'checksum_sha256' => str_repeat('a', 64)];
    $counts = $this->audit->inspect([$record], 'tenant-a', $this->disk);
    expect($counts['size_mismatch'])->toBe(1)
        ->and($counts['checksum_mismatch'])->toBe(1)
        ->and($counts['size_review_candidate'])->toBe(0)
        ->and($this->disk->get('original.bin'))->toBe('actual bytes')
        ->and($record['size_bytes'])->toBe(1);
});

it('distinguishes recoverable metadata from missing originals and absent checksums', function (): void {
    $this->disk->put('good', 'abc');
    $base = ['tenant_id' => 'a', 'disk' => 'tenant', 'size_bytes' => 99, 'checksum_sha256' => hash('sha256', 'abc')];
    $counts = $this->audit->inspect([
        $base + ['path' => 'good'],
        $base + ['path' => 'missing'],
        array_replace($base, ['path' => 'good', 'checksum_sha256' => null]),
        array_replace($base, ['path' => 'good', 'size_bytes' => 3]),
    ], 'a', $this->disk);
    expect($counts['scanned'])->toBe(4)->and($counts['missing_source'])->toBe(1)
        ->and($counts['size_review_candidate'])->toBe(1)->and($counts['checksum_unavailable'])->toBe(1)
        ->and($counts['verified'])->toBe(1);
});

it('rejects unsafe paths tenant leakage and unsupported disks before reads', function (array $override): void {
    $record = array_replace(['tenant_id' => 'a', 'disk' => 'tenant', 'path' => 'safe', 'size_bytes' => 0, 'checksum_sha256' => null], $override);
    $counts = $this->audit->inspect([$record], 'a', $this->disk);
    expect($counts['scope_rejected'])->toBe(1)->and($counts['missing_source'])->toBe(0);
})->with([
    [['path' => '../secret']], [['path' => '/etc/passwd']], [['path' => 'a/../secret']],
    [['path' => 'a\\secret']], [['path' => 'https://example.com/file']], [['path' => '']],
    [['tenant_id' => 'b']], [['disk' => 's3']], [['disk' => 'public']],
]);

it('refuses symlink escapes from the selected local root', function (): void {
    symlink(__FILE__, $this->root.'/escape');
    $counts = $this->audit->inspect([['tenant_id' => 'a', 'disk' => 'tenant', 'path' => 'escape']], 'a', $this->disk);
    expect($counts['scope_rejected'])->toBe(1);
});

it('reports empty input without claiming any verified originals', function (): void {
    $counts = $this->audit->inspect([], 'a', $this->disk);
    expect(array_sum($counts))->toBe(0);
});

it('reproduces five missing originals and fifteen checksum verified size discrepancies', function (): void {
    $this->disk->put('source', 'source');
    $records = [];
    for ($i = 0; $i < 20; $i++) {
        $records[] = ['tenant_id' => 'a', 'disk' => 'tenant', 'path' => $i < 5 ? 'missing-'.$i : 'source', 'size_bytes' => 1, 'checksum_sha256' => hash('sha256', 'source')];
    }
    $counts = $this->audit->inspect($records, 'a', $this->disk);
    expect($counts['missing_source'])->toBe(5)->and($counts['size_mismatch'])->toBe(15)
        ->and($counts['size_review_candidate'])->toBe(15)->and($counts['checksum_mismatch'])->toBe(0)
        ->and($this->disk->get('source'))->toBe('source');
});

it('counts read failures without exposing adapter exception messages', function (): void {
    $this->disk->put('source', 'source');
    $disk = Mockery::mock(Illuminate\Filesystem\FilesystemAdapter::class);
    $disk->shouldReceive('getConfig')->once()->andReturn(['driver' => 'local', 'root' => $this->root]);
    $disk->shouldReceive('readStream')->once()->with('source')->andThrow(new RuntimeException('private customer source'));
    $counts = $this->audit->inspect([['tenant_id' => 'a', 'disk' => 'tenant', 'path' => 'source']], 'a', $disk);
    expect($counts['read_failed'])->toBe(1)->and(json_encode($counts))->not->toContain('private');
});

it('refuses remote disk adapters before reading any objects', function (): void {
    $disk = Mockery::mock(Illuminate\Filesystem\FilesystemAdapter::class);
    $disk->shouldReceive('getConfig')->once()->andReturn(['driver' => 's3']);
    $disk->shouldNotReceive('readStream');
    $counts = $this->audit->inspect([['tenant_id' => 'a', 'disk' => 'tenant', 'path' => 'source']], 'a', $disk);
    expect($counts['scope_rejected'])->toBe(1);
});

it('rejects unsafe invocation before any tenant or storage resolution', function (string $environment, array $options, string $driver, string $message): void {
    $originalContainer = Container::getInstance();
    $originalFacade = Illuminate\Support\Facades\Facade::getFacadeApplication();
    $application = new Illuminate\Foundation\Application(dirname(__DIR__, 2));
    $context = new App\Services\Tenancy\TenantContext;
    $context->setActiveTenantId('existing-context');
    $application->instance(App\Services\Tenancy\TenantContext::class, $context);
    $application->instance('env', $environment);
    $application->instance('config', new Repository(['database' => ['connections' => ['landlord' => ['driver' => $driver === 'sqlite-url' ? 'sqlite' : $driver, 'url' => $driver === 'sqlite-url' ? 'postgres://refused.invalid/snapshot' : null]]]]));
    Illuminate\Support\Facades\Facade::clearResolvedInstances();
    Illuminate\Support\Facades\Facade::setFacadeApplication($application);
    try {
        $command = new App\Console\Commands\AuditLocalMediaIntegrity;
        $command->setLaravel($application);
        $tester = new Symfony\Component\Console\Tester\CommandTester($command);
        $options = array_replace(['--tenant' => 'private-tenant', '--root' => $this->root], $options);
        expect($tester->execute($options))->toBe(1)
            ->and($tester->getDisplay())->toContain($message)
            ->not->toContain('private-tenant', $this->root);
        expect($context->activeTenantId())->toBe('existing-context');
    } finally {
        Illuminate\Support\Facades\Facade::clearResolvedInstances();
        Illuminate\Support\Facades\Facade::setFacadeApplication($originalFacade);
        Container::setInstance($originalContainer);
    }
})->with([
    ['production', [], 'sqlite', 'Local/testing environments only'],
    ['local', ['--tenant' => ''], 'sqlite', 'Explicit tenant ID'],
    ['local', ['--root' => 's3://private'], 'sqlite', 'Explicit tenant ID'],
    ['local', [], 'pgsql', 'Remote databases and database URLs are refused'],
    ['local', [], 'sqlite-url', 'Remote databases and database URLs are refused'],
]);

it('validates empty and multi chunk originals and treats malformed hashes as unverified', function (): void {
    $large = str_repeat('data', 600000);
    $this->disk->put('large', $large);
    $this->disk->put('empty', '');
    $counts = $this->audit->inspect([
        ['tenant_id' => 'a', 'disk' => 'tenant', 'path' => 'large', 'size_bytes' => mb_strlen($large), 'checksum_sha256' => mb_strtoupper(hash('sha256', $large))],
        ['tenant_id' => 'a', 'disk' => 'tenant', 'path' => 'empty', 'size_bytes' => 0, 'checksum_sha256' => hash('sha256', '')],
        ['tenant_id' => 'a', 'disk' => 'tenant', 'path' => 'empty', 'size_bytes' => 0, 'checksum_sha256' => 'invalid'],
    ], 'a', $this->disk);
    expect($counts['verified'])->toBe(2)->and($counts['checksum_unavailable'])->toBe(1)
        ->and($counts['size_mismatch'])->toBe(0)->and($counts['read_failed'])->toBe(0);
});

it('counts bytes rather than Unicode characters for binary media', function (): void {
    $bytes = "\xc3\xa9\x00\xff";
    $this->disk->put('binary', $bytes);
    $counts = $this->audit->inspect([['tenant_id' => 'a', 'disk' => 'tenant', 'path' => 'binary', 'size_bytes' => 4, 'checksum_sha256' => hash('sha256', $bytes)]], 'a', $this->disk);
    expect($counts['verified'])->toBe(1)->and($counts['size_mismatch'])->toBe(0);
});

it('accepts only explicit local snapshot configurations without network calls', function (array $connection, bool $accepted, bool $invalidAdapter = false): void {
    $originalContainer = Container::getInstance();
    $originalFacade = Illuminate\Support\Facades\Facade::getFacadeApplication();
    $application = new Illuminate\Foundation\Application(dirname(__DIR__, 2));
    $application->instance('env', 'local');
    $application->instance('config', new Repository(['database' => ['connections' => ['landlord' => $connection]]]));
    if ($invalidAdapter) {
        $manager = Mockery::mock(FilesystemManager::class);
        $manager->shouldReceive('build')->once()->andReturn(Mockery::mock(Illuminate\Contracts\Filesystem\Filesystem::class));
        $application->instance('filesystem', $manager);
    } else {
        $application->singleton('filesystem', fn (): FilesystemManager => new FilesystemManager($application));
    }
    $audit = Mockery::mock(new LocalMediaIntegrityAudit);
    if ($accepted) {
        $audit->shouldReceive('recordsForTenant')->once()->with('selected-tenant')->andReturn([]);
        if ($invalidAdapter) {
            $audit->shouldNotReceive('inspect');
        } else {
            $audit->shouldReceive('inspect')->once()->with([], 'selected-tenant', Mockery::type(Illuminate\Filesystem\FilesystemAdapter::class))
                ->andReturn(['scanned' => 0, 'verified' => 0]);
        }
    } else {
        $audit->shouldNotReceive('recordsForTenant');
        $audit->shouldNotReceive('inspect');
    }
    $application->instance(LocalMediaIntegrityAudit::class, $audit);
    Illuminate\Support\Facades\Facade::clearResolvedInstances();
    Illuminate\Support\Facades\Facade::setFacadeApplication($application);
    try {
        $command = new App\Console\Commands\AuditLocalMediaIntegrity;
        $command->setLaravel($application);
        $tester = new Symfony\Component\Console\Tester\CommandTester($command);
        expect($tester->execute(['--tenant' => 'selected-tenant', '--root' => $this->root]))->toBe($accepted && ! $invalidAdapter ? 0 : 1);
        expect($tester->getDisplay())->not->toContain('selected-tenant', $this->root);
        if ($invalidAdapter) {
            expect($tester->getDisplay())->toContain('Local filesystem adapter required; nothing audited.');
        } elseif ($accepted) {
            expect($tester->getDisplay())->toContain('"scanned":0', 'no metadata was changed', 'Variants and orphan objects are not audited');
        } else {
            expect($tester->getDisplay())->toContain('Remote databases and database URLs are refused');
        }
    } finally {
        Illuminate\Support\Facades\Facade::clearResolvedInstances();
        Illuminate\Support\Facades\Facade::setFacadeApplication($originalFacade);
        Container::setInstance($originalContainer);
    }
})->with([
    [['driver' => 'sqlite', 'database' => ':memory:'], true],
    [['driver' => 'sqlite', 'database' => ':memory:'], true, true],
    [['driver' => 'pgsql', 'host' => 'localhost', 'database' => 'local_snapshot'], true],
    [['driver' => 'pgsql', 'host' => '127.0.0.1', 'database' => 'local_snapshot'], true],
    [['driver' => 'pgsql', 'host' => '::1', 'database' => 'local_snapshot'], true],
    [['driver' => 'pgsql', 'host' => 'remote.invalid', 'database' => 'snapshot'], false],
    [['driver' => 'pgsql', 'host' => '127.0.0.1', 'database' => ''], false],
    [['driver' => 'pgsql', 'database' => 'snapshot'], false],
    [['driver' => 'pgsql', 'host' => 'localhost', 'database' => 'snapshot', 'url' => 'postgres://remote.invalid/snapshot'], false],
]);
