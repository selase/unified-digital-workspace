<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantBillingAgreement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TenantBillingAgreement> */
final class TenantBillingAgreementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(), 'effective_from' => now()->startOfMonth(),
            'plan_slug' => 'essentials', 'currency' => 'GHS', 'editor_seats' => 2, 'setup_required' => false,
            'snapshot' => ['hybrid' => config('usage-pricing.hybrid'), 'meters' => config('usage-pricing.meters'), 'selected_packages' => [], 'dedicated_db' => false],
        ];
    }
}
