<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param list<string> $channels */
    public function __construct(
        public readonly string $event,
        public readonly string $title,
        public readonly string $body,
        public readonly array $channels,
        public readonly ?string $url = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title)->line($this->body);

        return $this->url !== null ? $mail->action('Open', $this->url) : $mail;
    }

    public function toSms(object $notifiable): string
    {
        return mb_substr($this->title.': '.$this->body, 0, 300);
    }

    /** @return array<string, string|null> */
    public function toArray(object $notifiable): array
    {
        return ['type' => $this->event, 'title' => $this->title, 'subject' => $this->body, 'action_url' => $this->url];
    }
}
