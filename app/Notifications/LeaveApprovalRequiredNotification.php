<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Modules\HrmsCore\Models\Leave\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class LeaveApprovalRequiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly LeaveRequest $leaveRequest
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
        $employeeName = $this->leaveRequest->employee?->displayName() ?? 'An employee';
        $categoryName = $this->leaveRequest->leaveCategory ? $this->leaveRequest->leaveCategory->name : 'Leave';
        $days = $this->leaveRequest->no_requested_days;
        $start = $this->leaveRequest->proposed_start_date->format('d/m/Y');
        $end = $this->leaveRequest->proposed_end_date->format('d/m/Y');

        return (new MailMessage)
            ->subject("[HRMS] Leave Approval Required: {$employeeName}")
            ->greeting('Leave Approval Required')
            ->line("{$employeeName} has submitted a request for {$days} day(s) of {$categoryName}.")
            ->line("Period: {$start} to {$end}")
            ->line('Reason: '.($this->leaveRequest->leave_reasons ?? 'None provided'))
            ->action('Review Leave Request', route('hrms-core.leave-requests.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $employeeName = $this->leaveRequest->employee?->displayName() ?? 'An employee';
        $categoryName = $this->leaveRequest->leaveCategory ? $this->leaveRequest->leaveCategory->name : 'Leave';
        $days = $this->leaveRequest->no_requested_days;
        $start = $this->leaveRequest->proposed_start_date->format('d/m/Y');
        $end = $this->leaveRequest->proposed_end_date->format('d/m/Y');

        return [
            'title' => "Leave Approval Required: {$employeeName}",
            'message' => "{$employeeName} has requested {$days} day(s) of {$categoryName} ({$start} - {$end}) awaiting your review.",
            'leave_request_id' => $this->leaveRequest->id,
            'employee_name' => $employeeName,
            'leave_category' => $categoryName,
            'days' => $days,
            'start_date' => $start,
            'end_date' => $end,
            'action_url' => route('hrms-core.leave-requests.index'),
        ];
    }
}
