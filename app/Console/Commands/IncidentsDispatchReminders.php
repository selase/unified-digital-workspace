<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\Incidents\IncidentReminder as IncidentReminderMail;
use App\Models\User;
use App\Modules\IncidentManagement\Models\Incident;
use App\Modules\IncidentManagement\Models\IncidentReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;

final class IncidentsDispatchReminders extends Command
{
    /**
     * @var string
     */
    protected $signature = 'incidents:dispatch-reminders';

    /**
     * @var string
     */
    protected $description = 'Dispatch scheduled incident reminders.';

    public function handle(): int
    {
        $now = now();

        $reminders = IncidentReminder::query()
            ->whereNull('sent_at')
            ->where('scheduled_for', '<=', $now)
            ->get();

        foreach ($reminders as $reminder) {
            $incident = Incident::query()->find($reminder->incident_id);

            if ($incident) {
                $userId = Arr::get($reminder->metadata ?? [], 'user_id') ?: $incident->assigned_to_id;

                if ($userId) {
                    $user = User::query()->find($userId);

                    if ($user && $user->email) {
                        Mail::to($user->email)->queue(new IncidentReminderMail($incident, $reminder));
                    }
                }
            }

            $reminder->sent_at = $now;
            $reminder->save();
        }

        $this->info("Incident reminders dispatched: {$reminders->count()}");

        return self::SUCCESS;
    }
}
