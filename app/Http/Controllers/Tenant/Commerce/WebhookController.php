<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantPaymentGateway;
use App\Services\Billing\PaymentFulfillmentService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class WebhookController extends Controller
{
    /**
     * Handle Stripe Webhooks for Merchant accounts.
     */
    public function handleStripe(Request $request, Tenant $tenant, PaymentFulfillmentService $fulfillmentService): JsonResponse
    {
        $gateway = TenantPaymentGateway::where('tenant_id', $tenant->id)
            ->where('provider', 'stripe')
            ->where('is_active', true)
            ->first();

        if (! $gateway) {
            return response()->json(['error' => 'Gateway not configured'], 404);
        }

        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = $gateway->webhook_secret_encrypted;

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, (string) $sigHeader, (string) $webhookSecret
            );
        } catch (Exception $e) {
            Log::error('Merchant Stripe webhook verification failed', ['tenant' => $tenant->id, 'error' => $e->getMessage()]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;
                $metadata = (array) ($session->metadata ?? []);
                $type = $metadata['type'] ?? null;
                $amount = (int) ($session->amount_total ?? 0);
                $txId = (string) ($session->payment_intent ?? $session->id ?? '');

                if (isset($metadata['tenant_id']) && (string) $metadata['tenant_id'] !== (string) $tenant->id) {
                    Log::warning('Merchant Stripe webhook tenant mismatch', ['tenant' => $tenant->id, 'metadata' => $metadata]);

                    return response()->json(['error' => 'Tenant mismatch'], 403);
                }

                if ($type === 'real_estate_installment') {
                    $fulfillmentService->fulfillRealEstateInstallment($metadata, $amount, $txId, 'stripe');
                } elseif ($type === 'escrow_funding') {
                    $fulfillmentService->fulfillEscrowFunding($metadata, $amount, $txId, 'stripe');
                } elseif ($type === 'store_order') {
                    $fulfillmentService->fulfillStoreOrder($metadata, $amount, $txId, 'stripe');
                } elseif ($type === 'platform_credit_purchase') {
                    $fulfillmentService->fulfillPlatformCreditPurchase($metadata, $amount, $txId, 'stripe');
                }

                $fulfillmentService->recordMerchantTransaction($tenant, 'stripe', $session);
                break;

            case 'payment_intent.succeeded':
                // Avoid double counting if using both
                break;
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle Paystack Webhooks for Merchant accounts.
     */
    public function handlePaystack(Request $request, Tenant $tenant, PaymentFulfillmentService $fulfillmentService): JsonResponse
    {
        $gateway = TenantPaymentGateway::where('tenant_id', $tenant->id)
            ->where('provider', 'paystack')
            ->where('is_active', true)
            ->first();

        if (! $gateway) {
            return response()->json(['error' => 'Gateway not configured'], 404);
        }

        $signature = $request->header('x-paystack-signature');
        $secret = $gateway->api_key_encrypted; // Paystack uses secret key for hashing

        if (! $signature || ! hash_equals(hash_hmac('sha512', $request->getContent(), (string) $secret), (string) $signature)) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $event = $request->input('event');
        $data = (array) $request->input('data', []);

        if ($event === 'charge.success') {
            $metadata = (array) ($data['metadata'] ?? []);
            $type = $metadata['type'] ?? null;
            $amount = (int) ($data['amount'] ?? 0);
            $txId = (string) ($data['reference'] ?? '');

            if (isset($metadata['tenant_id']) && (string) $metadata['tenant_id'] !== (string) $tenant->id) {
                Log::warning('Merchant Paystack webhook tenant mismatch', ['tenant' => $tenant->id, 'metadata' => $metadata]);

                return response()->json(['error' => 'Tenant mismatch'], 403);
            }

            if ($type === 'real_estate_installment') {
                $fulfillmentService->fulfillRealEstateInstallment($metadata, $amount, $txId, 'paystack');
            } elseif ($type === 'escrow_funding') {
                $fulfillmentService->fulfillEscrowFunding($metadata, $amount, $txId, 'paystack');
            } elseif ($type === 'store_order') {
                $fulfillmentService->fulfillStoreOrder($metadata, $amount, $txId, 'paystack');
            } elseif ($type === 'platform_credit_purchase') {
                $fulfillmentService->fulfillPlatformCreditPurchase($metadata, $amount, $txId, 'paystack');
            }

            $fulfillmentService->recordMerchantTransaction($tenant, 'paystack', (array) $data);
        }

        return response()->json(['status' => 'success']);
    }
}
