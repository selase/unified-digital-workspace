<?php

declare(strict_types=1);

use App\Console\Kernel;
use App\Models\Tenant;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;

function scheduledBackupEvents(): array
{
    $schedule = new Schedule;
    (new ReflectionMethod(Kernel::class, 'schedule'))->invoke(app(Kernel::class), $schedule);

    return array_values(array_filter($schedule->events(), fn ($event): bool => str_contains($event->command ?? '', 'backup:')));
}

it('backs up and monitors only S3 with exactly one success email source', function (): void {
    expect(config('backup.backup.destination.disks'))->toBe(['s3'])
        ->and(config('backup.monitor_backups.0.disks'))->toBe(['s3']);

    $channels = config('backup.notifications.notifications');
    expect($channels[BackupWasSuccessfulNotification::class])->toBe(['mail']);
    foreach ([HealthyBackupWasFoundNotification::class, CleanupWasSuccessfulNotification::class] as $notification) {
        expect($channels[$notification])->toBe([]);
    }
    foreach ([BackupHasFailedNotification::class, CleanupHasFailedNotification::class, UnhealthyBackupWasFoundNotification::class] as $notification) {
        expect($channels[$notification])->toBe(['mail']);
    }
});

it('schedules one daily backup and prevents overlapping backup maintenance', function (): void {
    $events = scheduledBackupEvents();
    expect($events)->toHaveCount(3);
    $expected = ['backup:clean' => '0 1 * * *', 'backup:run' => '30 1 * * *', 'backup:monitor' => '0 2 * * *'];
    foreach ($events as $event) {
        $name = collect(array_keys($expected))->first(fn (string $name): bool => str_contains($event->command, $name));
        expect($event->expression)->toBe($expected[$name])
            ->and($event->withoutOverlapping)->toBeTrue();
    }
});

it('fails scheduled preflight before any IO when an S3 setting is missing', function (string $setting, string $variable): void {
    Event::fake([BackupHasFailed::class]);
    config(['filesystems.disks.s3' => [
        'driver' => 's3', 'key' => 'test-key', 'secret' => 'test-secret', 'bucket' => 'test-bucket', 'region' => 'test-region',
    ]]);
    config(['filesystems.disks.s3.'.$setting => '']);

    foreach (scheduledBackupEvents() as $event) {
        expect(fn () => $event->callBeforeCallbacks(app()))->toThrow(RuntimeException::class, $variable);
    }

    Event::assertDispatchedTimes(BackupHasFailed::class, 3);
    Event::assertDispatched(BackupHasFailed::class, fn ($event): bool => ! str_contains($event->exception->getMessage(), 'test-secret'));
})->with([
    ['key', 'AWS_ACCESS_KEY_ID'], ['secret', 'AWS_SECRET_ACCESS_KEY'],
    ['bucket', 'AWS_BUCKET'], ['region', 'AWS_DEFAULT_REGION'],
]);

it('passes scheduled preflight with complete S3 configuration without writing a backup', function (): void {
    Event::fake([BackupHasFailed::class]);
    config(['filesystems.disks.s3' => [
        'driver' => 's3', 'key' => 'test-key', 'secret' => 'test-secret', 'bucket' => 'test-bucket', 'region' => 'test-region',
    ]]);
    foreach (scheduledBackupEvents() as $event) {
        $event->callBeforeCallbacks(app());
    }
    Event::assertNotDispatched(BackupHasFailed::class);
});

it('preserves the missing-settings error even when the failure notification cannot be delivered', function (): void {
    config(['filesystems.disks.s3.bucket' => '']);
    Event::shouldReceive('dispatch')
        ->once()
        ->with(Mockery::type(BackupHasFailed::class))
        ->andThrow(new RuntimeException('Mail transport unavailable'));

    $event = scheduledBackupEvents()[0];
    expect(fn () => $event->callBeforeCallbacks(app()))
        ->toThrow(RuntimeException::class, 'AWS_BUCKET');
});

it('blocks scheduled backups rather than claiming coverage of active dedicated databases', function (string $mode): void {
    Event::fake([BackupHasFailed::class]);
    config(['filesystems.disks.s3' => [
        'driver' => 's3', 'key' => 'test-key', 'secret' => 'test-secret', 'bucket' => 'test-bucket', 'region' => 'test-region',
    ]]);
    Tenant::factory()->create(['status' => 'active', 'isolation_mode' => $mode]);

    foreach (scheduledBackupEvents() as $event) {
        expect(fn () => $event->callBeforeCallbacks(app()))
            ->toThrow(RuntimeException::class, 'active dedicated tenant databases are not covered');
    }
    Event::assertDispatchedTimes(BackupHasFailed::class, 3);
})->with(['db_per_tenant', 'byo']);

it('allows the current shared-tenant CMS coverage without touching dedicated database connections', function (): void {
    Event::fake([BackupHasFailed::class]);
    config(['filesystems.disks.s3' => [
        'driver' => 's3', 'key' => 'test-key', 'secret' => 'test-secret', 'bucket' => 'test-bucket', 'region' => 'test-region',
    ]]);
    Tenant::factory()->create(['status' => 'active', 'isolation_mode' => 'shared']);
    Tenant::factory()->create(['status' => 'deactivated', 'isolation_mode' => 'db_per_tenant']);

    foreach (scheduledBackupEvents() as $event) {
        $event->callBeforeCallbacks(app());
    }
    Event::assertNotDispatched(BackupHasFailed::class);
});
