<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Transaction;

final class SubscriptionProvisioningService
{
    public function provision(Tenant $tenant, array $dto): void
    {
        // 1. Update Tenant Package
        if (isset($dto['package_id'])) {
            $tenant->update(['package_id' => $dto['package_id']]);
            $tenant->syncFeaturesFromPackage();
        }

        // 2. Create/Update Subscription
        Subscription::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'provider_id' => $dto['provider_subscription_id'],
            ],
            [
                'name' => 'default', // Or map from package
                'provider_status' => $dto['status'],
                'provider_plan' => $dto['provider_plan_id'],
                'current_period_end' => $dto['current_period_end'],
                // 'ends_at' => null // unless canceled
            ]
        );

        // 3. Create Transaction
        Transaction::create([
            'tenant_id' => $tenant->id,
            'provider' => $dto['provider'],
            'provider_transaction_id' => $dto['transaction_id'],
            'amount' => $dto['amount_paid'],
            'currency' => $dto['currency'],
            'status' => 'success', // or $dto['status'] mapping
            'type' => 'charge',
            'meta' => $dto,
        ]);
    }
}
