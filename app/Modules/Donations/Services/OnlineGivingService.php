<?php

declare(strict_types=1);

namespace App\Modules\Donations\Services;

use App\Models\Tenant;
use App\Modules\Donations\Models\DonCampaign;
use App\Modules\Donations\Models\DonDonation;
use App\Modules\Donations\Models\DonDonor;
use App\Modules\Finance\Contracts\FinancePaymentGateway;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Support\ActivateTenant;
use DomainException;

/** Lets the public give through the organisation's own payment gateway, and records the gift once it is confirmed. */
final class OnlineGivingService
{
    public function __construct(
        private readonly FinancePaymentGateway $gateway,
        private readonly DonationService $donations,
        private readonly FinanceSettings $settings,
    ) {}

    public function isEnabled(string $tenantId): bool
    {
        return $this->gateway->isConfigured($tenantId);
    }

    /**
     * Finds or creates the donor by email, then returns the gateway URL to send them to.
     *
     * @param  array{name: string, email: string, amount: int, campaign_id?: int|null}  $gift
     */
    public function checkout(Tenant $tenant, array $gift, string $callbackUrl): string
    {
        if (! $this->isEnabled($tenant->id)) {
            throw new DomainException('Online giving is not set up for this organisation yet.');
        }
        if ($gift['amount'] < 100) {
            throw new DomainException('The smallest online gift is 1.00.');
        }

        $campaign = isset($gift['campaign_id'])
            ? DonCampaign::query()->where('tenant_id', $tenant->id)->where('is_active', true)->find($gift['campaign_id'])
            : null;

        $donor = DonDonor::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'email' => mb_strtolower($gift['email'])],
            ['name' => $gift['name'], 'type' => 'individual'],
        );

        $session = $this->gateway->initialize(
            $tenant->id,
            $gift['email'],
            $gift['amount'],
            $this->settings->for($tenant->id)->base_currency,
            $callbackUrl,
            array_filter([
                'type' => 'donation',
                'tenant_id' => $tenant->id,
                'donor_id' => (string) $donor->id,
                'campaign_id' => $campaign ? (string) $campaign->id : null,
            ]),
        );

        return $session['url'];
    }

    /** Confirms the charge with the gateway and records the gift once. Null when it is not a paid donation for this tenant. */
    public function recordVerified(string $tenantId, string $reference): ?DonDonation
    {
        $result = $this->gateway->verify($tenantId, $reference);
        $metadata = $result['metadata'];

        if (! $result['paid'] || ($metadata['type'] ?? null) !== 'donation' || ($metadata['tenant_id'] ?? null) !== $tenantId || $result['amount'] <= 0) {
            return null;
        }

        $tenant = Tenant::query()->find($tenantId);
        if (! $tenant instanceof Tenant) {
            return null;
        }
        ActivateTenant::for($tenant);

        $donor = DonDonor::query()->where('tenant_id', $tenantId)->find((int) ($metadata['donor_id'] ?? 0));
        if (! $donor instanceof DonDonor) {
            return null;
        }

        return $this->donations->record($tenantId, $donor, $result['amount'], today(), array_filter([
            'method' => 'card',
            'reference' => $result['reference'],
            'gateway' => $this->gateway->key(),
            'gateway_reference' => $result['reference'],
            'campaign_id' => isset($metadata['campaign_id']) ? (int) $metadata['campaign_id'] : null,
        ], fn ($value): bool => $value !== null));
    }
}
