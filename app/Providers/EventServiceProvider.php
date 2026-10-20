<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\LoginLogs;
use App\Listeners\LogoutLogs;
use App\Listeners\SetTenantIdInSession;
use App\Listeners\UpdateLastLoginIp;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

final class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        Login::class => [
            UpdateLastLoginIp::class,
            LoginLogs::class,
            SetTenantIdInSession::class,
        ],

        Logout::class => [
            LogoutLogs::class,
        ],
    ];

    /**
     * The subscriber classes to register.
     *
     * @var array
     */
    protected $subscribe = [
        \App\Listeners\WebhookEventSubscriber::class,
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();

        $this->app->singleton(\App\Listeners\Tenancy\MeterTenantMail::class);
        $mailUsage = $this->app->make(\App\Listeners\Tenancy\MeterTenantMail::class);
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSent::class, [$mailUsage, 'handleMessageSent']);
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Queue\Events\JobProcessing::class, [$mailUsage, 'handleJobProcessing']);
        foreach ([
            \Illuminate\Queue\Events\JobProcessed::class,
            \Illuminate\Queue\Events\JobFailed::class,
            \Illuminate\Queue\Events\JobExceptionOccurred::class,
        ] as $event) {
            \Illuminate\Support\Facades\Event::listen($event, [$mailUsage, 'handleJobFinished']);
        }
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Notifications\Events\NotificationSending::class, [$mailUsage, 'handleNotificationSending']);
        foreach ([
            \Illuminate\Notifications\Events\NotificationSent::class,
            \Illuminate\Notifications\Events\NotificationFailed::class,
            \Illuminate\Notifications\Events\NotificationSkipped::class,
        ] as $event) {
            \Illuminate\Support\Facades\Event::listen($event, [$mailUsage, 'handleNotificationFinished']);
        }

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Notifications\Events\NotificationSending::class,
            [\App\Listeners\NotificationDeliveryCreditListener::class, 'handleSending']
        );

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Notifications\Events\NotificationSent::class,
            [\App\Listeners\NotificationDeliveryCreditListener::class, 'handleSent']
        );
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
