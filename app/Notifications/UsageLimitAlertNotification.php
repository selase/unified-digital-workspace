<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use App\Models\UsageLimit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class UsageLimitAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly UsageLimit $limit,
        public readonly float $percent
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $metricName = $this->limit->metric->value;
        $rounded = round($this->percent, 1);
        $period = ucfirst($this->limit->period);

        return (new MailMessage)
            ->subject("[Usage Alert] {$this->tenant->name}: {$metricName} quota reached {$rounded}%")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your organization **{$this->tenant->name}** has reached **{$rounded}%** of its {$metricName} allocation for this {$this->limit->period} period.")
            ->line("Limit Value: {$this->limit->limit_value} ({$period})")
            ->line("Alert Threshold: {$this->limit->alert_threshold}%")
            ->action('View Billing & Quotas', url('/billing'))
            ->line('Please upgrade your subscription or add platform credits to prevent service disruptions.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'usage_limit_alert',
            'tenant_id' => $this->tenant->id,
            'tenant_name' => $this->tenant->name,
            'metric' => $this->limit->metric->value,
            'percent' => round($this->percent, 1),
            'limit_value' => $this->limit->limit_value,
            'period' => $this->limit->period,
            'alert_threshold' => $this->limit->alert_threshold,
        ];
    }
}
