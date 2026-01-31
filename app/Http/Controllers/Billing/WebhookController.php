<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Billing\SubscriptionProvisioningService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

final class WebhookController extends Controller
{
    public function handleStripe(Request $request, SubscriptionProvisioningService $provisioningService)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                $endpointSecret
            );
        } catch (UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Handle the event
        switch ($event->type) {
            case 'invoice.payment_succeeded':
                $stripeInvoice = $event->data->object;
                $customerId = $stripeInvoice->customer;

                // Find Tenant
                $tenant = Tenant::where('meta->stripe_id', $customerId)->first();

                if ($tenant) {
                    $dto = [
                        'tenant_id' => $tenant->id,
                        'provider' => 'stripe',
                        'provider_subscription_id' => $stripeInvoice->subscription,
                        'provider_plan_id' => $stripeInvoice->lines->data[0]->price->id ?? null,
                        'status' => 'active',
                        'current_period_end' => isset($stripeInvoice->lines->data[0]->period->end)
                            ? Carbon::createFromTimestamp($stripeInvoice->lines->data[0]->period->end)
                            : now()->addMonth(),
                        'amount_paid' => $stripeInvoice->amount_paid,
                        'currency' => $stripeInvoice->currency,
                        'transaction_id' => $stripeInvoice->payment_intent ?? $stripeInvoice->id,
                    ];

                    $provisioningService->provision($tenant, $dto);
                    Log::info("Provisioned subscription for tenant {$tenant->id}");
                } else {
                    Log::warning("Tenant not found for Stripe Customer {$customerId}");
                }
                break;

            case 'checkout.session.completed':
                $session = $event->data->object;
                
                // If it's an invoice payment
                if (isset($session->metadata->invoice_id)) {
                    $invoice = \App\Models\Invoice::find($session->metadata->invoice_id);
                    if ($invoice && $invoice->status !== \App\Models\Invoice::STATUS_PAID) {
                        $invoice->update([
                            'status' => \App\Models\Invoice::STATUS_PAID,
                            'paid_at' => now(),
                        ]);

                        \App\Models\Transaction::create([
                            'tenant_id' => $invoice->tenant_id,
                            'invoice_id' => $invoice->id,
                            'amount' => $invoice->total * 100,
                            'currency' => $invoice->currency,
                            'status' => 'success',
                            'provider' => 'stripe',
                            'provider_transaction_id' => $session->payment_intent,
                        ]);
                        Log::info("Filled invoice {$invoice->id} via Stripe Webhook");
                    }
                }
                break;

            case 'customer.subscription.deleted':
                // Handle cancellation logic here
                break;

            default:
                Log::info('Received unknown Stripe event type '.$event->type);
        }

        return response()->json(['status' => 'success']);
    }
}
