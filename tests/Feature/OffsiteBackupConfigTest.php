<?php

declare(strict_types=1);

function loadBackupConfig(?string $bucket): array
{
    $bucket === null ? putenv('BACKUP_OFFSITE_BUCKET') : putenv('BACKUP_OFFSITE_BUCKET='.$bucket);

    return require config_path('backup.php');
}

afterEach(fn () => putenv('BACKUP_OFFSITE_BUCKET'));

it('stores and monitors backups on the offsite disk only when it is configured', function (): void {
    $withOffsite = loadBackupConfig('udworkspace-backups');
    expect($withOffsite['backup']['destination']['disks'])->toBe(['s3', 's3_offsite'])
        ->and($withOffsite['monitor_backups'][0]['disks'])->toBe(['s3', 's3_offsite']);

    $without = loadBackupConfig(null);
    expect($without['backup']['destination']['disks'])->toBe(['s3'])
        ->and($without['monitor_backups'][0]['disks'])->toBe(['s3']);
});

it('defines the offsite disk from its own credentials, separate from live media', function (): void {
    $disk = config('filesystems.disks.s3_offsite');

    expect($disk['driver'])->toBe('s3')->and($disk)->toHaveKeys(['key', 'secret', 'bucket', 'endpoint']);
});
