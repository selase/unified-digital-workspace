<?php

declare(strict_types=1);

use App\Listeners\SnapshotTenantMedia;
use App\Models\Tenant;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Events\BackupManifestWasCreated;
use Spatie\Backup\Tasks\Backup\Manifest;

beforeEach(function (): void {
    config(['filesystems.disks.s3' => ['driver' => 's3', 'key' => 'test-key', 'secret' => 'test-secret', 'bucket' => 'test-bucket', 'region' => 'test-region', 'root' => 'workspace']]);
    $this->source = Storage::fake('media-source');
    $this->root = sys_get_temp_dir().'/udw-media-'.bin2hex(random_bytes(8));
    mkdir($this->root.'/job', 0700, true);
    file_put_contents($this->root.'/application.php', '<?php');
    $this->manifest = Manifest::create($this->root.'/job/manifest.txt')->addFiles($this->root.'/application.php');
    Storage::partialMock()->shouldReceive('build')->andReturn($this->source)->byDefault();
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->root);
});

it('snapshots tenant-scoped S3 media with a restorable index and verified checksums', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'backup-customer', 's3_mode' => 'shared']);
    $this->source->put('media/photo.png', 'original photo');
    app(SnapshotTenantMedia::class)->handle(new BackupManifestWasCreated($this->manifest));
    $index = json_decode(file_get_contents($this->root.'/job/tenant-media/index.json'), true, flags: JSON_THROW_ON_ERROR);
    $entry = $index['tenants'][0]['files'][0];
    expect($index['schema_version'])->toBe(1)
        ->and($index['tenants'][0]['tenant_id'])->toBe((string) $tenant->id)
        ->and($entry['key'])->toBe('media/photo.png')
        ->and($entry['bytes'])->toBe(mb_strlen('original photo'))
        ->and($entry['sha256'])->toBe(hash('sha256', 'original photo'))
        ->and(file_get_contents($this->root.'/job/'.$entry['archive_path']))->toBe('original photo');
    expect(iterator_to_array($this->manifest->files()))->toContain($this->root.'/job/'.$entry['archive_path'], $this->root.'/job/tenant-media/index.json');
    Storage::shouldHaveReceived('build')->withArgs(fn (array $config): bool => $config['root'] === 'workspace/tenants/backup-customer' && $config['throw'] === true)->once();
    $this->source->delete('media/photo.png');
    expect(file_get_contents($this->root.'/job/'.$entry['archive_path']))->toBe('original photo');
});

it('retains media for inactive tenants and separates identical object keys by tenant', function (): void {
    $a = Tenant::factory()->create(['slug' => 'customer-a', 's3_mode' => 'shared', 'status' => 'active']);
    $b = Tenant::factory()->create(['slug' => 'customer-b', 's3_mode' => 'shared', 'status' => 'deactivated']);
    $this->source->put('same.txt', 'content');
    app(SnapshotTenantMedia::class)->handle(new BackupManifestWasCreated($this->manifest));
    $index = json_decode(file_get_contents($this->root.'/job/tenant-media/index.json'), true, flags: JSON_THROW_ON_ERROR);
    expect(array_column($index['tenants'], 'tenant_id'))->toContain((string) $a->id, (string) $b->id);
    expect($index['tenants'][0]['files'][0]['archive_path'])->not->toBe($index['tenants'][1]['files'][0]['archive_path']);
});

it('records empty tenant directories without inventing media', function (): void {
    Tenant::factory()->create(['s3_mode' => 'shared']);
    app(SnapshotTenantMedia::class)->handle(new BackupManifestWasCreated($this->manifest));
    $index = json_decode(file_get_contents($this->root.'/job/tenant-media/index.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($index['tenants'][0]['files'])->toBe([]);
});

it('fails the backup rather than silently skipping an unsupported BYO bucket', function (): void {
    Tenant::factory()->create(['s3_mode' => 'byo']);
    expect(fn () => app(SnapshotTenantMedia::class)->handle(new BackupManifestWasCreated($this->manifest)))->toThrow(RuntimeException::class, 'BYO');
    expect(file_exists($this->root.'/job/tenant-media/index.json'))->toBeFalse();
});

it('does not claim media coverage without configured shared storage', function (): void {
    Tenant::factory()->create(['s3_mode' => 'shared']);
    config(['filesystems.disks.s3.bucket' => '']);
    expect(fn () => app(SnapshotTenantMedia::class)->handle(new BackupManifestWasCreated($this->manifest)))->toThrow(RuntimeException::class, 'configured');
});

it('preserves database-only backup semantics', function (): void {
    $manifest = Manifest::create($this->root.'/job/db-only.txt')->addFiles($this->root.'/job/db.sql');
    file_put_contents($this->root.'/job/db.sql', '-- database dump');
    Tenant::factory()->create(['s3_mode' => 'shared']);
    app(SnapshotTenantMedia::class)->handle(new BackupManifestWasCreated($manifest));
    expect(file_exists($this->root.'/job/tenant-media'))->toBeFalse();
});

it('registers the media snapshot listener with the real backup event', function (): void {
    expect(app('events')->getRawListeners()[BackupManifestWasCreated::class] ?? [])->toContain(SnapshotTenantMedia::class);
});

it('produces a ZIP whose media can be restored from its index after source deletion', function (): void {
    config(['backup.backup.password' => null]);
    Tenant::factory()->create(['s3_mode' => 'shared']);
    $contents = "binary\0media\xff";
    $this->source->put('media/binary.dat', $contents);
    app(SnapshotTenantMedia::class)->handle(new BackupManifestWasCreated($this->manifest));
    $this->source->delete('media/binary.dat');
    Spatie\Backup\Tasks\Backup\Zip::createForManifest($this->manifest, $this->root.'/job/archive.zip');
    $archive = new ZipArchive;
    expect($archive->open($this->root.'/job/archive.zip'))->toBeTrue();
    try {
        $index = json_decode($archive->getFromName('tenant-media/index.json'), true, flags: JSON_THROW_ON_ERROR);
        $entry = $index['tenants'][0]['files'][0];
        $restored = $archive->getFromName($entry['archive_path']);
        expect($restored)->toBe($contents)
            ->and(hash('sha256', $restored))->toBe($entry['sha256']);
    } finally {
        $archive->close();
    }
});

it('aborts partial media copies without logging source secrets or leaving a successful index', function (bool $missing): void {
    Tenant::factory()->create(['s3_mode' => 'shared']);
    $disk = Mockery::mock(Illuminate\Filesystem\FilesystemAdapter::class);
    $disk->shouldReceive('allFiles')->once()->andReturn(['media/problem.dat']);
    if ($missing) {
        $disk->shouldReceive('readStream')->once()->andThrow(new RuntimeException('AWS_SECRET_ACCESS_KEY=secret-value'));
    } else {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, 'short');
        rewind($stream);
        $disk->shouldReceive('readStream')->once()->andReturn($stream);
        $disk->shouldReceive('size')->once()->andReturn(99);
    }
    Storage::shouldReceive('build')->andReturn($disk);
    $caught = null;
    try {
        app(SnapshotTenantMedia::class)->handle(new BackupManifestWasCreated($this->manifest));
    } catch (RuntimeException $error) {
        $caught = $error;
    }
    expect($caught)->toBeInstanceOf(RuntimeException::class);
    expect($caught->getMessage())->toContain('snapshot failed')->not->toContain('secret-value');
    expect(file_exists($this->root.'/job/tenant-media'))->toBeFalse()
        ->and(iterator_to_array($this->manifest->files()))->toBe([$this->root.'/application.php']);
})->with([true, false]);

it('refuses unsafe tenant storage prefixes', function (): void {
    Tenant::factory()->create(['slug' => '../another-app', 's3_mode' => 'shared']);
    expect(fn () => app(SnapshotTenantMedia::class)->handle(new BackupManifestWasCreated($this->manifest)))->toThrow(RuntimeException::class, 'snapshot failed');
});
