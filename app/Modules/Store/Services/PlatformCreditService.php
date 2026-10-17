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
    public function purchasePackage(string $tenantId, int|string $packageId): TenantCreditWallet
    {
        $package = PlatformCreditPackage::where('id', $packageId)
            ->where('is_active', true)
            ->firstOrFail();

        return DB::transaction(function () use ($tenantId, $package): TenantCreditWallet {
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

            return $wallet->fresh();
        });
    }

    /**
     * Atomically deducts quota strictly upon delivery verification.
     */
    public function consumeCredit(string $tenantId, string $creditType, int $amount = 1): bool
    {
        if ($amount <= 0) {
            return true;
        }

        $affected = TenantCreditWallet::where('tenant_id', $tenantId)
            ->where('credit_type', $creditType)
            ->where('balance', '>=', $amount)
            ->decrement('balance', $amount);

        if ($affected > 0) {
            TenantCreditWallet::where('tenant_id', $tenantId)
                ->where('credit_type', $creditType)
                ->increment('total_consumed', $amount);

            return true;
        }

        return false;
    }
}
