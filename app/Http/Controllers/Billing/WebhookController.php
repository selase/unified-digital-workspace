<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\Billing\PaymentFulfillmentService;
use App\Services\Billing\SubscriptionProvisioningService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
                /** @var \Stripe\Checkout\Session $session */
                $session = $event->data->object;
                $metadata = $session->metadata?->toArray() ?? [];
                $type = $metadata['type'] ?? null;
                $amount = (int) ($session->amount_total ?? 0);
                $txId = (string) ($session->payment_intent ?? $session->id ?? '');
                $currency = mb_strtoupper((string) ($session->currency ?? $metadata['currency'] ?? 'USD'));

                // If it's an invoice payment
                if (isset($metadata['invoice_id'])) {
                    $invoice = Invoice::find($metadata['invoice_id']);
                    if ($invoice && $session->payment_status === 'paid') {
                        $txId = (string) ($session->payment_intent ?? $session->id ?? '');
                        try {
                            $fulfillmentService->fulfillInvoicePayment($invoice, $txId, 'stripe', $amount, (string) ($session->currency ?? ''), $metadata);
                        } catch (DomainException $exception) {
                            return response()->json(['error' => 'Invoice payment mismatch'], 422);
                        }
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

    public function handlePaystack(Request $request, PaymentFulfillmentService $fulfillment): JsonResponse
    {
        $secret = (string) config('services.paystack.secret_key');
        $signature = $request->header('x-paystack-signature');
        if ($secret === '' || ! is_string($signature) || ! hash_equals(hash_hmac('sha512', $request->getContent(), $secret), $signature)) {
            return $this->paystackResponse($request, ['error' => 'Invalid signature'], 'invalid_signature', 400);
        }
        $request->attributes->set('payment_webhook.signature_valid', true);
        $event = $request->input('event');
        if (is_string($event) && preg_match('/^[a-z][a-z0-9_.]{0,79}$/D', $event)) {
            $request->attributes->set('payment_webhook.event', $event);
        }
        if ($event !== 'charge.success') {
            return $this->paystackResponse($request, ['status' => 'ignored'], 'ignored_event');
        }
        $reference = $request->input('data.reference');
        if (! is_string($reference) || ! preg_match('/^[A-Za-z0-9_.=-]{1,200}$/D', $reference)) {
            return $this->paystackResponse($request, ['error' => 'Invalid reference'], 'invalid_reference', 400);
        }
        $request->attributes->set('payment_webhook.reference', $reference);
        $result = app(\App\Services\Payment\PaystackGateway::class)->verifyTransaction($reference);
        if (($result['status'] ?? '') !== 'success') {
            return $this->paystackResponse($request, ['error' => 'Payment not confirmed'], 'payment_unconfirmed', 422);
        }
        $transactionId = $result['transaction_id'] ?? null;
        if ((is_string($transactionId) || is_int($transactionId)) && preg_match('/^[A-Za-z0-9_.=-]{1,200}$/D', (string) $transactionId)) {
            $request->attributes->set('payment_webhook.provider_transaction_id', (string) $transactionId);
        }
        $invoiceId = data_get($result, 'metadata.invoice_id');
        $application = data_get($result, 'metadata.application');
        if ($application !== null && $application !== 'udworkspace') {
            return $this->paystackResponse($request, ['status' => 'ignored'], 'ignored_application');
        }
        if (! is_string($invoiceId) || ! Str::isUuid($invoiceId)) {
            return $this->paystackResponse($request, ['status' => 'ignored'], 'ignored_invoice');
        }
        $invoice = Invoice::query()->find($invoiceId);
        if (! $invoice) {
            return $this->paystackResponse($request, ['status' => 'ignored'], 'ignored_invoice');
        }
        $request->attributes->set('payment_webhook.invoice_id', $invoiceId);
        $wasAlreadySettled = $invoice->status === Invoice::STATUS_PAID
            && data_get($invoice->meta, 'provider') === 'paystack'
            && data_get($invoice->meta, 'payment_reference') === (string) ($result['transaction_id'] ?? '');
        try {
            $settled = $fulfillment->fulfillInvoicePayment($invoice, (string) ($result['transaction_id'] ?? ''), 'paystack',
                (int) ($result['amount'] ?? 0), (string) ($result['currency'] ?? ''), (array) ($result['metadata'] ?? []));
        } catch (DomainException $exception) {
            return $this->paystackResponse($request, ['error' => 'Invoice payment mismatch'], 'payment_mismatch', 422);
        }

        $outcome = $wasAlreadySettled ? 'duplicate' : 'processed';
        if (data_get($settled->meta, 'payment_reference') !== (string) ($result['transaction_id'] ?? '')) {
            $outcome = 'reconciliation_required';
        }

        return $this->paystackResponse($request, ['status' => 'success'], $outcome);
    }

    /** @param array<string, string> $body */
    private function paystackResponse(Request $request, array $body, string $outcome, int $status = 200): JsonResponse
    {
        $request->attributes->set('payment_webhook.outcome', $outcome);

        return response()->json($body, $status);
    }
}
