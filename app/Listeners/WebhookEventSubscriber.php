<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Jobs\SendWebhookJob;
use App\Models\WebhookEndpoint;
// Or actual Event class
// Assuming we have events. If not, we might be listening to Eloquent events or specific domain events.
// For now, I'll assume we have 'App\Events\InvoiceIssued' or similar, or I'll implement a generic dispatch.

use Illuminate\Events\Dispatcher;

final class WebhookEventSubscriber
{
    /**
     * Handle Invoice Issued events.
     */
    public function handleInvoiceIssued($event): void
    {
        // $event->invoice should allow us to get the tenant
        if (! isset($event->invoice)) {
            return;
        }

        $invoice = $event->invoice;
        $tenantId = $invoice->tenant_id;

        $this->dispatchWebhooks($tenantId, 'invoice.issued', [
            'invoice_id' => $invoice->number,
            'amount' => $invoice->total,
            'status' => $invoice->status,
            'url' => route('billing.invoices.show', $invoice), // Admin url, or public url if exists
        ]);
    }

    /**
     * Handle User Created / Registered events.
     */
    public function handleUserCreated(mixed $event): void
    {
        $user = $event->user ?? null;
        if (! $user) {
            return;
        }

        $tenantId = (string) ($user->tenant_id ?? '');
        if (! $tenantId) {
            return;
        }

        $this->dispatchWebhooks($tenantId, 'user.created', [
            'user_id' => (string) ($user->uuid ?? $user->id),
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'created_at' => $user->created_at?->toIso8601String(),
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            \App\Events\InvoiceIssued::class,
            [self::class, 'handleInvoiceIssued']
        );

        $events->listen(
            \Illuminate\Auth\Events\Registered::class,
            [self::class, 'handleUserCreated']
        );
    }

    /**
     * Generic dispatcher.
     */
    private function dispatchWebhooks(string $tenantId, string $eventName, array $payload): void
    {
        // Find active endpoints for this tenant that subscribe to this event (or all events)
        $endpoints = WebhookEndpoint::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        foreach ($endpoints as $endpoint) {
            // Check if endpoint subscribes to this event
            // events is array of strings, or null for "all", or contains "*"
            $subscribedEvents = $endpoint->events ?? [];

            if (empty($subscribedEvents) || in_array('*', $subscribedEvents) || in_array($eventName, $subscribedEvents)) {
                SendWebhookJob::dispatch($endpoint, $eventName, $payload);
            }
        }
    }
}
