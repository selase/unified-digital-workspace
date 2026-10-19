<?php

declare(strict_types=1);

namespace App\Modules\Store\Services;

use App\Modules\Store\Models\PlatformCreditPackage;
use App\Modules\Store\Models\TenantCreditWallet;
use Illuminate\Support\Facades\DB;

final class PlatformCreditService
{
    /**
     * Atomically purchases and credits a platform package to a tenant wallet.
     */
    public function purchasePackage(string $tenantId, int|string $packageId, ?string $paymentReference = null): TenantCreditWallet
    {
        $package = PlatformCreditPackage::where('id', $packageId)
            ->where('is_active', true)
            ->firstOrFail();

        return DB::connection('tenant')->transaction(function () use ($tenantId, $package, $paymentReference): TenantCreditWallet {
            if ($paymentReference !== null) {
                $existing = \App\Modules\Store\Models\PlatformCreditPurchase::where('tenant_id', $tenantId)
                    ->where('payment_reference', $paymentReference)
                    ->first();

                if ($existing) {
                    /** @var TenantCreditWallet $existingWallet */
                    $existingWallet = TenantCreditWallet::where('tenant_id', $tenantId)
                        ->where('credit_type', $package->credit_type)
                        ->firstOrFail();

                    return $existingWallet;
                }
            }

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

            if ($paymentReference !== null) {
                \App\Modules\Store\Models\PlatformCreditPurchase::create([
                    'tenant_id' => $tenantId,
                    'package_id' => $package->id,
                    'payment_reference' => $paymentReference,
                    'amount_paid_cents' => (int) $package->price_cents,
                    'credits_awarded' => (int) $package->amount_credits,
                    'status' => 'succeeded',
                ]);
            }

            return $wallet->fresh() ?? $wallet;
        });
    }

    /**
     * Checks if a tenant has sufficient credit balance.
     */
    public function hasBalance(string $tenantId, string $creditType, int $amount = 1): bool
    {
        $wallet = TenantCreditWallet::where('tenant_id', $tenantId)
            ->where(function ($q) use ($creditType): void {
                $q->where('credit_type', $creditType);
                if ($creditType === 'ai_token') {
                    $q->orWhere('credit_type', 'ai_tokens');
                } elseif ($creditType === 'ai_tokens') {
                    $q->orWhere('credit_type', 'ai_token');
                }
            })
            ->first();

        if (! $wallet) {
            return false;
        }

        return $wallet->balance >= $amount;
    }

    /**
     * Get current balance for tenant and credit type.
     */
    public function getBalance(string $tenantId, string $creditType): int
    {
        $wallet = TenantCreditWallet::where('tenant_id', $tenantId)
            ->where(function ($q) use ($creditType): void {
                $q->where('credit_type', $creditType);
                if ($creditType === 'ai_token') {
                    $q->orWhere('credit_type', 'ai_tokens');
                } elseif ($creditType === 'ai_tokens') {
                    $q->orWhere('credit_type', 'ai_token');
                }
            })
            ->first();

        return $wallet ? (int) $wallet->balance : 0;
    }

    /**
     * Atomically deducts quota strictly upon delivery verification.
     */
    public function consumeCredit(string $tenantId, string $creditType, int $amount = 1): bool
    {
        if ($amount <= 0) {
            return true;
        }

        return DB::connection('tenant')->transaction(function () use ($tenantId, $creditType, $amount): bool {
            $wallet = TenantCreditWallet::where('tenant_id', $tenantId)
                ->where(function ($q) use ($creditType): void {
                    $q->where('credit_type', $creditType);
                    if ($creditType === 'ai_token') {
                        $q->orWhere('credit_type', 'ai_tokens');
                    } elseif ($creditType === 'ai_tokens') {
                        $q->orWhere('credit_type', 'ai_token');
                    }
                })
                ->where('balance', '>=', $amount)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                return false;
            }

            $wallet->decrement('balance', $amount);
            $wallet->increment('total_consumed', $amount);

            return true;
        });
    }
}
