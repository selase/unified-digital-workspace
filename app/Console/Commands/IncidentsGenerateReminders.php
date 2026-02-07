<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\IncidentManagement\Services\IncidentReminderService;
use Illuminate\Console\Command;

final class IncidentsGenerateReminders extends Command
{
    /**
     * @var string
     */
    protected $signature = 'incidents:generate-reminders';

    /**
     * @var string
     */
    protected $description = 'Generate upcoming incident reminder records.';

    public function handle(IncidentReminderService $service): int
    {
        $count = $service->generateUpcomingReminders();

        $this->info("Incident reminders generated: {$count}");

        return self::SUCCESS;
    }
}
