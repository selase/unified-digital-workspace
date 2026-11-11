<?php

declare(strict_types=1);

namespace App\Modules\Donations\Services;

use App\Models\Tenant;
use App\Modules\Donations\Mail\DonationReceiptMail;
use App\Modules\Donations\Models\DonCampaign;
use App\Modules\Donations\Models\DonDonation;
use App\Modules\Donations\Models\DonDonor;
use App\Modules\Donations\Models\DonPledge;
use App\Modules\Finance\Contracts\FinancePaymentGateway;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Support\ActivateTenant;
use DomainException;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Lets the public give through the organisation's own payment gateway, and records the gift once it is confirmed. */
final class OnlineGivingService
{
    public function __construct(
        private readonly FinancePaymentGateway $gateway,
        private readonly DonationService $donations,
        private readonly FinanceSettings $settings,
        private readonly PledgeService $pledges,
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

    /** Starts a checkout for the pledge's usual amount and returns the gateway URL. Used from a payment link sent to the donor. */
    public function pledgeCheckout(DonPledge $pledge, string $callbackUrl, bool $allowAutoCharge = false): string
    {
        if (! $this->isEnabled($pledge->tenant_id)) {
            throw new DomainException('Online giving is not set up for this organisation yet.');
        }
        if ($pledge->status !== DonPledge::STATUS_ACTIVE) {
            throw new DomainException('This pledge is not active.');
        }

        $pledge->loadMissing('donor');
        if (! $pledge->donor->email) {
            throw new DomainException('This donor has no email address on file, so online payment is unavailable.');
        }

        return $this->gateway->initialize(
            $pledge->tenant_id,
            $pledge->donor->email,
            $pledge->amount,
            $this->settings->for($pledge->tenant_id)->base_currency,
            $callbackUrl,
            array_filter([
                'type' => 'donation',
                'tenant_id' => $pledge->tenant_id,
                'donor_id' => (string) $pledge->donor_id,
                'campaign_id' => $pledge->campaign_id ? (string) $pledge->campaign_id : null,
                'pledge_id' => (string) $pledge->id,
                'auto_charge' => $allowAutoCharge ? '1' : null,
            ]),
        )['url'];
    }

    /** A link the donor can open to pay the next instalment. It stays valid for 30 days. */
    public function pledgeLink(DonPledge $pledge, string $tenantSlug): string
    {
        $generator = clone app('url');
        $root = mb_rtrim((string) config('app.url'), '/');
        $generator->forceRootUrl($root);
        $generator->forceScheme(parse_url($root, PHP_URL_SCHEME) ?: 'https');

        return $generator->temporarySignedRoute('donations.public.pledge', now()->addDays(30), ['tenant' => $tenantSlug, 'pledge' => $pledge->uuid]);
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

        $pledge = isset($metadata['pledge_id'])
            ? DonPledge::query()->where('tenant_id', $tenantId)->where('donor_id', $donor->id)->find((int) $metadata['pledge_id'])
            : null;

        $gift = $this->donations->record($tenantId, $donor, $result['amount'], today(), array_filter([
            'method' => 'card',
            'pledge_id' => $pledge?->id,
            'fund_id' => $pledge?->fund_id,
            'reference' => $result['reference'],
            'gateway' => $this->gateway->key(),
            'gateway_reference' => $result['reference'],
            'campaign_id' => isset($metadata['campaign_id']) ? (int) $metadata['campaign_id'] : null,
        ], fn ($value): bool => $value !== null));

        if ($pledge instanceof DonPledge && $gift->wasRecentlyCreated && $pledge->status === DonPledge::STATUS_ACTIVE) {
            $this->pledges->advanceSchedule($pledge);
            $this->keepCardIfAllowed($pledge->fresh(), $metadata, $result['authorization'] ?? []);
        }

        $this->sendReceipt($gift, $donor, $pledge instanceof DonPledge && $pledge->fresh()->canAutoCharge() ? $this->stopLink($pledge, $tenant->slug) : null);

        return $gift;
    }

    /**
     * Records the money from an automatic charge: the gift, the receipt, and the next due date.
     * Safe to call twice for the same reference.
     */
    public function recordAutomaticCharge(DonPledge $pledge, string $reference, int $amount): DonDonation
    {
        $pledge->loadMissing('donor');
        $gift = $this->donations->record($pledge->tenant_id, $pledge->donor, $amount, today(), array_filter([
            'method' => 'card',
            'reference' => $reference,
            'gateway' => $this->gateway->key(),
            'gateway_reference' => $reference,
            'campaign_id' => $pledge->campaign_id,
            'fund_id' => $pledge->fund_id,
            'pledge_id' => $pledge->id,
        ], fn ($value): bool => $value !== null));

        if ($gift->wasRecentlyCreated && $pledge->status === DonPledge::STATUS_ACTIVE) {
            $this->pledges->advanceSchedule($pledge);
            $pledge->forceFill(['charge_failures' => 0, 'last_charge_error' => null])->save();
        }

        $slug = (string) Tenant::query()->whereKey($pledge->tenant_id)->value('slug');
        $this->sendReceipt($gift, $pledge->donor, $this->stopLink($pledge->fresh(), $slug));

        return $gift;
    }

    /** A signed link the donor can use to stop automatic charges without logging in. */
    public function stopLink(DonPledge $pledge, string $tenantSlug): string
    {
        $generator = clone app('url');
        $root = mb_rtrim((string) config('app.url'), '/');
        $generator->forceRootUrl($root);
        $generator->forceScheme(parse_url($root, PHP_URL_SCHEME) ?: 'https');

        return $generator->signedRoute('donations.public.pledge-stop', ['tenant' => $tenantSlug, 'pledge' => $pledge->uuid]);
    }

    /**
     * Keeps the card only when the donor ticked the consent box and the card can be used again.
     *
     * @param  array<string, mixed>  $metadata
     * @param  array{code?: string|null, reusable?: bool, last4?: string|null, brand?: string|null}  $authorization
     */
    private function keepCardIfAllowed(?DonPledge $pledge, array $metadata, array $authorization): void
    {
        if (! $pledge instanceof DonPledge || ($metadata['auto_charge'] ?? null) !== '1' || ! ($authorization['reusable'] ?? false) || empty($authorization['code'])) {
            return;
        }

        $pledge->forceFill([
            'auto_charge' => true,
            'authorization_code' => $authorization['code'],
            'card_last4' => $authorization['last4'] ?? null,
            'card_brand' => $authorization['brand'] ?? null,
            'consent_at' => now(),
            'charge_failures' => 0,
            'last_charge_error' => null,
        ])->save();
    }

    /** Emails the receipt once. A mail problem must never undo a gift that has already been recorded. */
    private function sendReceipt(DonDonation $gift, DonDonor $donor, ?string $stopUrl = null): void
    {
        if ($gift->receipt_sent_at !== null || ! $donor->email) {
            return;
        }

        try {
            $profile = $this->settings->for($gift->tenant_id);
            Mail::to($donor->email)->send(new DonationReceiptMail($gift, $profile->legal_name ?: (string) config('app.name'), $profile->email, $stopUrl));
            $gift->forceFill(['receipt_sent_at' => now()])->save();
        } catch (Throwable $error) {
            report($error);
        }
    }
}
