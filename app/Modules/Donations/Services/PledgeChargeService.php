<?php

declare(strict_types=1);

namespace App\Modules\Donations\Services;

use App\Models\Tenant;
use App\Models\TenantModule;
use App\Modules\Donations\Models\DonPledge;
use App\Modules\Finance\Contracts\FinancePaymentGateway;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Support\ActivateTenant;
use App\Modules\Finance\Support\Money;
use App\Services\Alerts\AlertDispatcher;
use App\Services\Alerts\AlertEvent;
use Throwable;

/**
 * Charges the cards donors have allowed us to keep, on the day an instalment is due. One attempt a day, and after
 * three failures the card is dropped and staff are told, so a donor is never charged repeatedly or silently.
 */
final class PledgeChargeService
{
    public const MAX_FAILURES = 3;

    public function __construct(
        private readonly FinancePaymentGateway $gateway,
        private readonly OnlineGivingService $giving,
        private readonly FinanceSettings $settings,
        private readonly AlertDispatcher $alerts,
    ) {}

    /** Charges everything due across all organisations using Donations. Returns [charged, failed]. */
    public function chargeAllDue(): array
    {
        $charged = 0;
        $failed = 0;

        $tenantIds = TenantModule::query()->where('module_slug', 'donations')->where('is_enabled', true)->pluck('tenant_id');
        foreach (Tenant::query()->whereIn('id', $tenantIds)->get() as $tenant) {
            try {
                ActivateTenant::for($tenant);
                [$ok, $bad] = $this->chargeDue($tenant);
                $charged += $ok;
                $failed += $bad;
            } catch (Throwable $error) {
                report($error);
            }
        }

        return [$charged, $failed];
    }

    /** @return array{0: int, 1: int} */
    public function chargeDue(Tenant $tenant): array
    {
        $charged = 0;
        $failed = 0;

        $due = DonPledge::query()->where('tenant_id', $tenant->id)->where('status', DonPledge::STATUS_ACTIVE)->where('auto_charge', true)->whereNotNull('authorization_code')
            ->whereDate('next_due_on', '<=', today()->toDateString())
            ->where(fn ($query) => $query->whereNull('last_charge_attempt_on')->orWhereDate('last_charge_attempt_on', '<', today()->toDateString()))
            ->with('donor')->get();

        foreach ($due as $pledge) {
            $this->charge($tenant, $pledge) ? $charged++ : $failed++;
        }

        return [$charged, $failed];
    }

    public function charge(Tenant $tenant, DonPledge $pledge): bool
    {
        if (! $pledge->canAutoCharge() || ! $pledge->donor->email) {
            return false;
        }

        $reference = 'plg-'.$pledge->uuid.'-'.$pledge->next_due_on->format('Ymd');
        $pledge->forceFill(['last_charge_attempt_on' => today()])->save();

        try {
            $result = $this->gateway->chargeAuthorization($pledge->tenant_id, $pledge->donor->email, $pledge->amount, $this->settings->for($pledge->tenant_id)->base_currency, (string) $pledge->authorization_code, $reference, [
                'type' => 'donation', 'tenant_id' => $pledge->tenant_id, 'donor_id' => (string) $pledge->donor_id, 'pledge_id' => (string) $pledge->id,
            ]);
        } catch (Throwable $error) {
            report($error);
            $result = ['paid' => false, 'amount' => 0, 'reference' => $reference, 'message' => 'The payment service could not be reached.'];
        }

        if ($result['paid'] && $result['amount'] === $pledge->amount) {
            $this->giving->recordAutomaticCharge($pledge, $result['reference'], $result['amount']);

            return true;
        }

        $this->recordFailure($tenant, $pledge, $result['message']);

        return false;
    }

    public function stopAutoCharge(DonPledge $pledge): DonPledge
    {
        $pledge->forceFill(['auto_charge' => false, 'authorization_code' => null, 'card_last4' => null, 'card_brand' => null, 'charge_failures' => 0, 'last_charge_error' => null])->save();

        return $pledge;
    }

    private function recordFailure(Tenant $tenant, DonPledge $pledge, string $message): void
    {
        $failures = $pledge->charge_failures + 1;
        $pledge->forceFill(['charge_failures' => $failures, 'last_charge_error' => mb_substr($message, 0, 250)])->save();

        $dropped = $failures >= self::MAX_FAILURES;
        if ($dropped) {
            $this->stopAutoCharge($pledge)->forceFill(['last_charge_error' => mb_substr($message, 0, 250)])->save();
        }

        $this->alerts->dispatchSafely(
            $tenant,
            AlertEvent::PLEDGE_CHARGE_FAILED,
            'Automatic pledge charge failed',
            $pledge->donor->name.': '.Money::format($pledge->amount, $this->settings->for($tenant->id)->base_currency).' could not be charged ('.$message.').'.($dropped ? ' The saved card has been removed after '.self::MAX_FAILURES.' failures; send the donor a payment link.' : ' It will be tried again tomorrow.'),
            'pledge-charge-'.$pledge->id.'-'.today()->format('Ymd'),
            route('donations.pledges.index', [], false),
        );
    }
}
