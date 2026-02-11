<?php

declare(strict_types=1);

namespace App\Notifications\QualityMonitoring;

use App\Modules\QualityMonitoring\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class QualityAlertEscalationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Alert $alert,
        private readonly int $level,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'alert_id' => $this->alert->id,
            'type' => $this->alert->type,
            'status' => $this->alert->status,
            'workplan_id' => $this->alert->workplan_id,
            'kpi_id' => $this->alert->kpi_id,
            'metadata' => $this->alert->metadata,
            'escalation_level' => $this->level,
        ];
    }
}
