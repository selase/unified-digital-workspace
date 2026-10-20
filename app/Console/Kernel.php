<?php

declare(strict_types=1);

namespace App\Console;

use App\Enum\TenantStatusEnum;
use App\Models\Tenant;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use RuntimeException;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Health\Commands\RunHealthChecksCommand;

final class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command(RunHealthChecksCommand::class)->everyMinute();
        $schedule->command('backup:clean')->daily()->at('01:00')
            ->withoutOverlapping()
            ->before(fn () => $this->assertBackupDestinationConfigured());
        $schedule->command('backup:run')->daily()->at('01:30')
            ->withoutOverlapping()
            ->before(fn () => $this->assertBackupDestinationConfigured());
        $schedule->command('backup:monitor')->dailyAt('02:00')
            ->withoutOverlapping()
            ->before(fn () => $this->assertBackupDestinationConfigured());

        // Usage Metering & Billing
        $schedule->command('tenants:audit-storage')->dailyAt('03:00');
        $schedule->command('tenants:audit-db')->dailyAt('03:30');
        $schedule->command('usage:process-rollups --period=hour')->hourly();
        $schedule->command('usage:process-rollups --period=day')->daily();
        $schedule->command('usage:prune')->dailyAt('04:00');
        $schedule->command('usage:check-alerts')->dailyAt('09:00'); // Check limits daily

        // Invoicing
        $schedule->command('billing:generate-invoices')->monthlyOn(1, '05:00');
        $schedule->command('billing:send-reminders')->dailyAt('08:00')->withoutOverlapping();

        // Incident Management
        $schedule->command('incidents:check-sla')->hourly();
        $schedule->command('incidents:generate-reminders')->hourlyAt(15);
        $schedule->command('incidents:dispatch-reminders')->everyTenMinutes();

        // Quality Monitoring
        $schedule->command('quality:generate-alerts')->dailyAt('08:00');
        $schedule->command('quality:escalate-alerts')->dailyAt('08:30');

        // Facilities Management
        $schedule->command('facilities:generate-preventive-work-orders')->dailyAt('06:00');

        // Document Management ISO Reviews & Expirations
        $schedule->command('documents:check-reviews')->dailyAt('07:00');

        // HRMS Annual Leave Rollover
        $schedule->command('hrms:leave-rollover')->yearlyOn(1, 1, '00:05');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    private function assertBackupDestinationConfigured(): void
    {
        $required = [
            'bucket' => 'AWS_BUCKET',
            'region' => 'AWS_DEFAULT_REGION',
            'key' => 'AWS_ACCESS_KEY_ID',
            'secret' => 'AWS_SECRET_ACCESS_KEY',
        ];
        $missing = [];

        foreach ($required as $setting => $variable) {
            $value = config('filesystems.disks.s3.'.$setting);
            if (! is_string($value) || mb_trim($value) === '') {
                $missing[] = $variable;
            }
        }

        if ($missing !== []) {
            $message = 'S3 backup preflight failed. Missing configuration: '.implode(', ', $missing).'. No backup or maintenance was started.';
        } elseif (Tenant::query()
            ->where('status', TenantStatusEnum::ACTIVE)
            ->whereIn('isolation_mode', ['db_per_tenant', 'byo'])
            ->exists()) {
            $message = 'S3 backup preflight failed: active dedicated tenant databases are not covered by the configured main-database-only backup. No backup or maintenance was started.';
        } else {
            return;
        }

        $exception = new RuntimeException($message);
        try {
            event(new BackupHasFailed($exception));
        } finally {
            throw $exception;
        }
    }
}
