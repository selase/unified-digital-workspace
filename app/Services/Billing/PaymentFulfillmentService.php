<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\MerchantTransaction;
use App\Models\Tenant;
use App\Models\Transaction;
use DomainException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class PaymentFulfillmentService
{
    /**
     * Atomically fulfill an invoice payment idempotently.
     */
    public function fulfillInvoicePayment(Invoice $invoice, string $providerTransactionId, string $provider = 'stripe'): Invoice
    {
        return DB::connection('landlord')->transaction(function () use ($invoice, $providerTransactionId, $provider): Invoice {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            if ($lockedInvoice->status === Invoice::STATUS_PAID) {
                Log::info("Invoice {$lockedInvoice->id} already fulfilled (idempotent skip).");

                return $lockedInvoice;
            }

            $meta = (array) ($lockedInvoice->meta ?? []);
            $meta['paid_at'] = now()->toIso8601String();

            $lockedInvoice->update([
                'status' => Invoice::STATUS_PAID,
                'meta' => $meta,
            ]);

            // Ensure Transaction record is idempotently created
            Transaction::firstOrCreate(
                [
                    'tenant_id' => $lockedInvoice->tenant_id,
                    'provider' => $provider,
                    'provider_transaction_id' => $providerTransactionId,
                ],
                [
                    'amount' => (int) round((float) $lockedInvoice->total * 100),
                    'currency' => $lockedInvoice->currency,
                    'status' => 'success',
                    'type' => 'charge',
                    'meta' => [
                        'invoice_id' => $lockedInvoice->id,
                        'invoice_number' => $lockedInvoice->number,
                    ],
                ]
            );

            Log::info("Fulfilled invoice {$lockedInvoice->id} via {$provider} transaction {$providerTransactionId}.");

            return $lockedInvoice;
        });
    }

    /**
     * Atomically fulfill an LLM token purchase idempotently.
     *
     * @return array{status: string, tokens: int, pack_key: string}
     */
    public function fulfillLlmTokenPurchase(Tenant $tenant, string $packKey, string $providerTransactionId, string $provider = 'stripe'): array
    {
        $packs = Config::get('llm.token_packs', []);

        if (! isset($packs[$packKey])) {
            Log::error("Invalid token pack {$packKey} on fulfillment for tenant {$tenant->id}");
            throw new DomainException("Invalid token pack {$packKey}");
        }

        $pack = $packs[$packKey];
        $tokens = (int) $pack['tokens'];

        return DB::connection('landlord')->transaction(function () use ($tenant, $packKey, $pack, $tokens, $providerTransactionId, $provider): array {
            $existing = Transaction::where('tenant_id', $tenant->id)
                ->where('provider_transaction_id', $providerTransactionId)
                ->first();

            if ($existing) {
                Log::info("LLM token purchase {$providerTransactionId} for tenant {$tenant->id} already fulfilled (idempotent skip).");

                return [
                    'status' => 'already_fulfilled',
                    'tokens' => $tokens,
                    'pack_key' => $packKey,
                ];
            }

            DB::connection('landlord')->table('tenants')
                ->where('id', $tenant->id)
                ->increment('llm_topup_balance', $tokens);

            Transaction::create([
                'tenant_id' => $tenant->id,
                'amount' => (int) round((float) $pack['price'] * 100),
                'currency' => $pack['currency'],
                'status' => 'success',
                'type' => 'credit',
                'provider' => $provider,
                'provider_transaction_id' => $providerTransactionId,
                'meta' => [
                    'type' => 'llm_token_purchase',
                    'pack_key' => $packKey,
                    'tokens' => $tokens,
                ],
            ]);

            Log::info("Credited {$tokens} tokens to tenant {$tenant->id} via transaction {$providerTransactionId}.");

            return [
                'status' => 'fulfilled',
                'tokens' => $tokens,
                'pack_key' => $packKey,
            ];
        });
    }

    /**
     * Atomically record merchant transactions idempotently.
     *
     * @param  object|array<string, mixed>  $data
     */
    public function recordMerchantTransaction(Tenant $tenant, string $provider, object|array $data): MerchantTransaction
    {
        $isObject = is_object($data);

        $providerTransactionId = $provider === 'stripe'
            ? ($isObject ? ($data->payment_intent ?? $data->id ?? '') : ($data['payment_intent'] ?? $data['id'] ?? ''))
            : ($isObject ? ($data->reference ?? '') : ($data['reference'] ?? ''));

        $amount = $provider === 'stripe'
            ? ($isObject ? ($data->amount_total ?? $data->amount ?? 0) : ($data['amount_total'] ?? $data['amount'] ?? 0))
            : ($isObject ? ($data->amount ?? 0) : ($data['amount'] ?? 0));

        $rawCurrency = $provider === 'stripe'
            ? ($isObject ? ($data->currency ?? 'USD') : ($data['currency'] ?? 'USD'))
            : ($isObject ? ($data->currency ?? 'NGN') : ($data['currency'] ?? 'NGN'));

        $currency = mb_strtoupper((string) $rawCurrency);

        $customerEmail = $provider === 'stripe'
            ? ($isObject ? ($data->customer_details->email ?? null) : ($data['customer_details']['email'] ?? null))
            : ($isObject ? ($data->customer->email ?? null) : ($data['customer']['email'] ?? null));

        $customerName = $provider === 'stripe'
            ? ($isObject ? ($data->customer_details->name ?? null) : ($data['customer_details']['name'] ?? null))
            : ($isObject
                ? mb_trim(($data->customer->first_name ?? '').' '.($data->customer->last_name ?? ''))
                : mb_trim(($data['customer']['first_name'] ?? '').' '.($data['customer']['last_name'] ?? '')));

        /** @var MerchantTransaction $record */
        $record = MerchantTransaction::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'provider' => $provider,
                'provider_transaction_id' => (string) $providerTransactionId,
            ],
            [
                'amount' => (int) $amount,
                'currency' => $currency,
                'status' => 'succeeded',
                'type' => 'payment',
                'customer_email' => $customerEmail,
                'customer_name' => $customerName ?: null,
                'description' => $provider === 'stripe' ? 'Stripe Checkout' : 'Paystack Charge',
                'meta' => (array) $data,
            ]
        );

        return $record;
    }
}
