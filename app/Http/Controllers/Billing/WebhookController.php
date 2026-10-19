<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\Billing\PaymentFulfillmentService;
use App\Services\Billing\SubscriptionProvisioningService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

final class WebhookController extends Controller
{
    public function handleStripe(
        Request $request,
        SubscriptionProvisioningService $provisioningService,
        PaymentFulfillmentService $fulfillmentService
    ): JsonResponse {
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
                $metadata = (array) ($session->metadata ?? []);
                $type = $metadata['type'] ?? null;
                $amount = (int) ($session->amount_total ?? 0);
                $txId = (string) ($session->payment_intent ?? $session->id ?? '');
                $currency = mb_strtoupper((string) ($session->currency ?? $metadata['currency'] ?? 'USD'));

                // If it's an invoice payment
                if (isset($metadata['invoice_id'])) {
                    $invoice = Invoice::find($metadata['invoice_id']);
                    if ($invoice) {
                        $txId = (string) ($session->payment_intent ?? $session->id ?? $invoice->number);
                        $fulfillmentService->fulfillInvoicePayment($invoice, $txId, 'stripe');
                        Log::info("Filled invoice {$invoice->id} via Stripe Webhook");
                    }
                } elseif ($type === 'real_estate_installment') {
                    $fulfillmentService->fulfillRealEstateInstallment($metadata, $amount, $txId, 'stripe', $currency);
                } elseif ($type === 'escrow_funding') {
                    $fulfillmentService->fulfillEscrowFunding($metadata, $amount, $txId, 'stripe', $currency);
                } elseif ($type === 'store_order') {
                    $fulfillmentService->fulfillStoreOrder($metadata, $amount, $txId, 'stripe', $currency);
                } elseif ($type === 'platform_credit_purchase') {
                    $fulfillmentService->fulfillPlatformCreditPurchase($metadata, $amount, $txId, 'stripe', $currency);
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
