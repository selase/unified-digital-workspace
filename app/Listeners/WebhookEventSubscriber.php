<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Jobs\SendWebhookJob;
use App\Models\WebhookEndpoint;
use App\Notifications\InvoiceIssued; // Or actual Event class
// Assuming we have events. If not, we might be listening to Eloquent events or specific domain events.
// For now, I'll assume we have 'App\Events\InvoiceIssued' or similar, or I'll implement a generic dispatch.

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Events\Dispatcher;

class WebhookEventSubscriber
{
    /**
     * Handle Invoice Issued events.
     */
    public function handleInvoiceIssued($event): void
    {
        // $event->invoice should allow us to get the tenant
        if (!isset($event->invoice)) return;

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
     * Generic dispatcher.
     */
    protected function dispatchWebhooks(string $tenantId, string $eventName, array $payload): void
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

    // Example 2: User Created
    public function handleUserCreated($event): void
    {
       // todo
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @param  \Illuminate\Events\Dispatcher  $events
     * @return void
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            \App\Events\InvoiceIssued::class,
            [self::class, 'handleInvoiceIssued']
        );
    }
}
