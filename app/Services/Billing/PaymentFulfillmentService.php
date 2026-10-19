<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\MerchantTransaction;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Modules\RealEstate\Models\ClientPlotAllocation;
use App\Modules\RealEstate\Models\InstallmentSchedule;
use App\Modules\Store\Models\PlatformCreditPackage;
use App\Modules\Store\Models\PlatformCreditPurchase;
use App\Modules\Store\Models\StoreOrder;
use App\Modules\Store\Models\TenantCreditWallet;
use App\Modules\Store\Services\StoreInventoryService;
use App\Modules\VendorMarketplace\Models\EscrowAccount;
use App\Modules\VendorMarketplace\Models\ProformaInvoice;
use App\Modules\VendorMarketplace\Services\EscrowSettlementService;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use DomainException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class PaymentFulfillmentService
{
    public function __construct(
        private readonly EscrowSettlementService $escrowSettlementService,
        private readonly StoreInventoryService $storeInventoryService
    ) {}

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

    /**
     * Atomically fulfill a Real Estate installment payment idempotently.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function fulfillRealEstateInstallment(
        array $metadata,
        int $amountPesewas,
        string $providerTransactionId,
        string $provider = 'paystack',
        string $currency = 'GHS'
    ): InstallmentSchedule {
        $installmentId = $metadata['installment_id'] ?? null;
        $tenantId = (string) ($metadata['tenant_id'] ?? '');

        if (! $installmentId || ! $tenantId) {
            throw new DomainException('Invalid metadata for real estate installment payment.');
        }

        if (mb_strtoupper($currency) !== 'GHS') {
            throw new DomainException("Currency mismatch for installment payment: expected GHS, received {$currency}.");
        }

        $isOverpayment = false;
        /** @var InstallmentSchedule|null $overpaymentInstallment */
        $overpaymentInstallment = null;

        $this->connectTenantDatabase($tenantId);

        $result = DB::connection('tenant')->transaction(function () use ($installmentId, $tenantId, $amountPesewas, $providerTransactionId, $provider, $currency, &$isOverpayment, &$overpaymentInstallment): ?InstallmentSchedule {
            /** @var InstallmentSchedule $installment */
            $installment = InstallmentSchedule::withoutGlobalScopes()
                ->where('id', $installmentId)
                ->lockForUpdate()
                ->firstOrFail();

            // Cross-tenant verification
            if ((string) $installment->tenant_id !== $tenantId) {
                throw new DomainException('Tenant mismatch for real estate installment payment.');
            }

            $activeTenantId = app(TenantContext::class)->activeTenantId();
            if ($activeTenantId && (string) $activeTenantId !== (string) $installment->tenant_id) {
                throw new DomainException('Cross-tenant violation: active tenant does not match installment tenant.');
            }

            // Idempotency check: if already recorded with this provider transaction id
            if ($installment->payment_reference === $providerTransactionId && $installment->status === 'paid') {
                Log::info("Installment {$installment->id} already fulfilled via {$providerTransactionId} (idempotent skip).");

                return $installment;
            }

            $due = (int) $installment->amount_due_pesewas;
            $alreadyPaid = (int) $installment->amount_paid_pesewas;
            $remainingDue = max(0, $due - $alreadyPaid);

            // Rejection of second payment when nothing remains due / already paid -> record for refund
            if ($remainingDue === 0 || $installment->status === 'paid') {
                $isOverpayment = true;
                $overpaymentInstallment = $installment;

                return null;
            }

            // Amount verification: reject underpayment
            if ($amountPesewas < $remainingDue) {
                throw new DomainException("Underpayment detected: paid {$amountPesewas} pesewas, but {$remainingDue} pesewas was due.");
            }

            $newPaid = $alreadyPaid + $amountPesewas;

            $installment->update([
                'amount_paid_pesewas' => $newPaid,
                'status' => 'paid',
                'paid_at' => now(),
                'payment_reference' => $providerTransactionId,
            ]);

            /** @var ClientPlotAllocation|null $allocation */
            $allocation = $installment->allocation()->lockForUpdate()->first();
            if ($allocation) {
                $allocation->increment('amount_paid_pesewas', $amountPesewas);
                if ($allocation->amount_paid_pesewas >= $allocation->total_price_pesewas) {
                    $allocation->update(['status' => 'completed']);
                    $allocation->parcel()->update(['status' => 'sold']);
                }
            }

            // Create Transaction ledger row
            Transaction::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'provider' => $provider,
                    'provider_transaction_id' => $providerTransactionId,
                ],
                [
                    'amount' => $amountPesewas,
                    'currency' => $currency,
                    'status' => 'success',
                    'type' => 'real_estate_installment',
                    'meta' => [
                        'installment_id' => $installment->id,
                        'allocation_id' => $installment->client_plot_allocation_id,
                        'installment_number' => $installment->installment_number,
                    ],
                ]
            );

            Log::info("Fulfilled real estate installment {$installment->id} via {$providerTransactionId}.");

            return $installment->fresh() ?? $installment;
        });

        if ($isOverpayment && $overpaymentInstallment) {
            Transaction::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'provider' => $provider,
                    'provider_transaction_id' => $providerTransactionId,
                ],
                [
                    'amount' => $amountPesewas,
                    'currency' => $currency,
                    'status' => 'refund_pending',
                    'type' => 'real_estate_installment_refund',
                    'meta' => [
                        'reason' => 'Installment already fully paid - duplicate payment flagged for refund',
                        'installment_id' => $overpaymentInstallment->id,
                        'allocation_id' => $overpaymentInstallment->client_plot_allocation_id,
                    ],
                ]
            );

            activity('real_estate')
                ->performedOn($overpaymentInstallment)
                ->withProperties([
                    'provider_transaction_id' => $providerTransactionId,
                    'amount_pesewas' => $amountPesewas,
                    'status' => 'refund_pending',
                ])
                ->log('installment_overpayment_rejected_for_refund');

            throw new DomainException("Installment {$overpaymentInstallment->id} is already fully paid. Payment {$providerTransactionId} has been rejected and recorded for refund.");
        }

        return $result ?? InstallmentSchedule::withoutGlobalScopes()->where('id', $installmentId)->firstOrFail();
    }

    /**
     * Atomically fulfill escrow funding for a proforma invoice idempotently.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function fulfillEscrowFunding(
        array $metadata,
        int $amountPesewas,
        string $providerTransactionId,
        string $provider = 'paystack',
        string $currency = 'GHS'
    ): EscrowAccount {
        $proformaId = $metadata['proforma_id'] ?? null;
        $tenantId = (string) ($metadata['tenant_id'] ?? '');
        $userUuid = (string) ($metadata['user_uuid'] ?? '');

        if (! $proformaId || ! $tenantId) {
            throw new DomainException('Invalid metadata for escrow funding.');
        }

        $this->connectTenantDatabase($tenantId);

        return DB::connection('tenant')->transaction(function () use ($proformaId, $tenantId, $userUuid, $amountPesewas, $providerTransactionId, $provider, $currency): EscrowAccount {
            /** @var ProformaInvoice $proforma */
            $proforma = ProformaInvoice::withoutGlobalScopes()
                ->where('id', $proformaId)
                ->lockForUpdate()
                ->firstOrFail();

            // Cross-tenant verification
            if ((string) $proforma->tenant_id !== $tenantId) {
                throw new DomainException('Tenant mismatch for escrow funding.');
            }

            $activeTenantId = app(TenantContext::class)->activeTenantId();
            if ($activeTenantId && (string) $activeTenantId !== (string) $proforma->tenant_id) {
                throw new DomainException('Cross-tenant violation: active tenant does not match proforma tenant.');
            }

            // Currency verification
            $expectedCurrency = mb_strtoupper((string) ($proforma->currency ?: 'GHS'));
            if (mb_strtoupper($currency) !== $expectedCurrency) {
                throw new DomainException("Currency mismatch for escrow funding: expected {$expectedCurrency}, received {$currency}.");
            }

            // Amount verification
            if ($amountPesewas < (int) $proforma->gross_amount_pesewas) {
                throw new DomainException("Underpayment detected: paid {$amountPesewas} pesewas, but {$proforma->gross_amount_pesewas} pesewas was due.");
            }

            // Idempotency handled by EscrowSettlementService
            $idempotencyKey = "gateway:{$providerTransactionId}";
            $escrow = $this->escrowSettlementService->fundFromProforma(
                $proforma,
                $userUuid ?: ($proforma->client_id ?? 'system'),
                $idempotencyKey
            );

            // Create Transaction ledger row
            Transaction::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'provider' => $provider,
                    'provider_transaction_id' => $providerTransactionId,
                ],
                [
                    'amount' => $amountPesewas,
                    'currency' => $currency,
                    'status' => 'success',
                    'type' => 'escrow_funding',
                    'meta' => [
                        'proforma_id' => $proforma->id,
                        'invoice_number' => $proforma->invoice_number,
                        'escrow_account_id' => $escrow->id,
                    ],
                ]
            );

            Log::info("Fulfilled escrow funding for proforma {$proforma->id} via {$providerTransactionId}.");

            return $escrow;
        });
    }

    /**
     * Atomically fulfill a store order payment idempotently.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function fulfillStoreOrder(
        array $metadata,
        int $amountPesewas,
        string $providerTransactionId,
        string $provider = 'paystack',
        string $currency = 'GHS'
    ): StoreOrder {
        $orderId = $metadata['order_id'] ?? null;
        $tenantId = (string) ($metadata['tenant_id'] ?? '');

        if (! $orderId || ! $tenantId) {
            throw new DomainException('Invalid metadata for store order.');
        }

        $this->connectTenantDatabase($tenantId);

        return DB::connection('tenant')->transaction(function () use ($orderId, $tenantId, $amountPesewas, $providerTransactionId, $provider, $currency): StoreOrder {
            /** @var StoreOrder $order */
            $order = StoreOrder::withoutGlobalScopes()
                ->where('id', $orderId)
                ->lockForUpdate()
                ->firstOrFail();

            // Cross-tenant verification
            if ((string) $order->tenant_id !== $tenantId) {
                throw new DomainException('Tenant mismatch for store order payment.');
            }

            $activeTenantId = app(TenantContext::class)->activeTenantId();
            if ($activeTenantId && (string) $activeTenantId !== (string) $order->tenant_id) {
                throw new DomainException('Cross-tenant violation: active tenant does not match store order tenant.');
            }

            // Currency verification
            $expectedCurrency = mb_strtoupper((string) ($order->currency ?: 'GHS'));
            if (mb_strtoupper($currency) !== $expectedCurrency) {
                throw new DomainException("Currency mismatch for store order: expected {$expectedCurrency}, received {$currency}.");
            }

            // Idempotency check
            if ($order->status === 'paid' && $order->payment_reference === $providerTransactionId) {
                Log::info("Store order {$order->id} already fulfilled via {$providerTransactionId} (idempotent skip).");

                return $order;
            }

            // Amount verification
            if ($amountPesewas < (int) $order->total_amount_cents) {
                throw new DomainException("Underpayment detected: paid {$amountPesewas} cents, but {$order->total_amount_cents} cents was due.");
            }

            $fulfilledOrder = $this->storeInventoryService->confirmPayment($order, $providerTransactionId);

            // Create Transaction ledger row
            Transaction::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'provider' => $provider,
                    'provider_transaction_id' => $providerTransactionId,
                ],
                [
                    'amount' => $amountPesewas,
                    'currency' => $currency,
                    'status' => 'success',
                    'type' => 'store_order',
                    'meta' => [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                    ],
                ]
            );

            Log::info("Fulfilled store order {$order->id} via {$providerTransactionId}.");

            return $fulfilledOrder;
        });
    }

    /**
     * Atomically fulfill a platform credit purchase idempotently.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function fulfillPlatformCreditPurchase(
        array $metadata,
        int $amountPesewas,
        string $providerTransactionId,
        string $provider = 'paystack',
        string $currency = 'GHS'
    ): PlatformCreditPurchase {
        $packageId = $metadata['package_id'] ?? null;
        $purchaseId = $metadata['purchase_id'] ?? null;
        $tenantId = (string) ($metadata['tenant_id'] ?? '');

        if (! $packageId || ! $tenantId) {
            throw new DomainException('Invalid metadata for platform credit purchase.');
        }

        $this->connectTenantDatabase($tenantId);

        return DB::connection('tenant')->transaction(function () use ($packageId, $purchaseId, $tenantId, $amountPesewas, $providerTransactionId, $provider, $currency): PlatformCreditPurchase {
            /** @var PlatformCreditPackage $package */
            $package = PlatformCreditPackage::findOrFail($packageId);

            /** @var PlatformCreditPurchase|null $purchase */
            $purchase = null;
            if ($purchaseId) {
                $purchase = PlatformCreditPurchase::withoutGlobalScopes()
                    ->where('id', $purchaseId)
                    ->lockForUpdate()
                    ->first();

                if ($purchase && (string) $purchase->tenant_id !== $tenantId) {
                    throw new DomainException('Tenant mismatch for platform credit purchase.');
                }
            }

            $activeTenantId = app(TenantContext::class)->activeTenantId();
            if ($activeTenantId && (string) $activeTenantId !== $tenantId) {
                throw new DomainException('Cross-tenant violation: active tenant does not match credit purchase tenant.');
            }

            // Currency verification
            $expectedCurrency = mb_strtoupper((string) ($package->currency ?: 'GHS'));
            if (mb_strtoupper($currency) !== $expectedCurrency) {
                throw new DomainException("Currency mismatch for platform credit purchase: expected {$expectedCurrency}, received {$currency}.");
            }

            // Idempotency check: if already fulfilled with this provider transaction id
            $existing = PlatformCreditPurchase::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('payment_reference', $providerTransactionId)
                ->where('status', 'succeeded')
                ->first();

            if ($existing) {
                Log::info("Platform credit purchase {$providerTransactionId} already fulfilled (idempotent skip).");

                return $existing;
            }

            if ($purchase && $purchase->status === 'succeeded' && $purchase->payment_reference === $providerTransactionId) {
                Log::info("Platform credit purchase {$purchase->id} already fulfilled (idempotent skip).");

                return $purchase;
            }

            // Amount verification
            if ($amountPesewas < (int) $package->price_cents) {
                throw new DomainException("Underpayment detected: paid {$amountPesewas} cents, but {$package->price_cents} cents was due.");
            }

            // Credit the tenant wallet
            /** @var TenantCreditWallet $wallet */
            $wallet = TenantCreditWallet::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'credit_type' => $package->credit_type,
                ],
                [
                    'balance' => 0,
                    'total_purchased' => 0,
                    'total_consumed' => 0,
                ]
            );

            $wallet->increment('balance', $package->amount_credits);
            $wallet->increment('total_purchased', $package->amount_credits);

            if ($purchase) {
                $purchase->update([
                    'payment_reference' => $providerTransactionId,
                    'amount_paid_cents' => (int) $package->price_cents,
                    'credits_awarded' => (int) $package->amount_credits,
                    'status' => 'succeeded',
                ]);
                $record = $purchase->fresh() ?? $purchase;
            } else {
                $record = PlatformCreditPurchase::create([
                    'tenant_id' => $tenantId,
                    'package_id' => $package->id,
                    'payment_reference' => $providerTransactionId,
                    'amount_paid_cents' => (int) $package->price_cents,
                    'credits_awarded' => (int) $package->amount_credits,
                    'status' => 'succeeded',
                ]);
            }

            // Create Transaction ledger row
            Transaction::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'provider' => $provider,
                    'provider_transaction_id' => $providerTransactionId,
                ],
                [
                    'amount' => $amountPesewas,
                    'currency' => $currency,
                    'status' => 'success',
                    'type' => 'platform_credit_purchase',
                    'meta' => [
                        'package_id' => $package->id,
                        'credits_awarded' => $package->amount_credits,
                        'credit_type' => $package->credit_type,
                    ],
                ]
            );

            Log::info("Fulfilled platform credit purchase for tenant {$tenantId} via {$providerTransactionId}.");

            return $record;
        });
    }

    /**
     * Point the `tenant` connection at the tenant that owns the payment.
     *
     * Webhooks and gateway callbacks arrive without a resolved tenant, so the
     * tenant connection is unconfigured (or still pointing at another tenant's
     * database). The tenant id comes from gateway-signed metadata. If a tenant is
     * already active in this request it must be the same one.
     */
    private function connectTenantDatabase(string $tenantId): void
    {
        $context = app(TenantContext::class);
        $activeTenantId = $context->activeTenantId();

        if ($activeTenantId && (string) $activeTenantId !== $tenantId) {
            throw new DomainException('Tenant mismatch: the active tenant does not match the tenant that owns this payment.');
        }

        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant instanceof Tenant) {
            throw new DomainException("Unknown tenant {$tenantId} for payment fulfillment.");
        }

        app(TenantDatabaseManager::class)->activate($tenant);
    }
}
