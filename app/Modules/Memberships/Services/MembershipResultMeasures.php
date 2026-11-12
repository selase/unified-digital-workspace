<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Services;

use App\Modules\Finance\Models\FinInvoice;
use App\Modules\Finance\Models\FinPaymentAllocation;
use App\Modules\Memberships\Models\MemMember;
use App\Modules\Memberships\Models\MemRenewal;
use App\Modules\ResultsFramework\Contracts\ResultMeasureProvider;
use App\Modules\ResultsFramework\Support\HasNoMeasureBreakdowns;
use App\Modules\ResultsFramework\Support\MeasureDefinition;
use App\Modules\ResultsFramework\Support\MeasureQuery;
use DomainException;

final class MembershipResultMeasures implements ResultMeasureProvider
{
    use HasNoMeasureBreakdowns;

    public function measures(): array
    {
        return [
            new MeasureDefinition('memberships.active_members', 'Active members (current roster)', 'memberships', 'people'),
            new MeasureDefinition('memberships.new_members', 'New members', 'memberships', 'people'),
            new MeasureDefinition('memberships.dues_collected', 'Membership dues collected', 'memberships', 'currency'),
        ];
    }

    public function value(string $tenantId, string $measureKey, MeasureQuery $query): float
    {
        $members = MemMember::query()->where('tenant_id', $tenantId);
        $end = $query->to->toDateString();

        return match ($measureKey) {
            'memberships.active_members' => (float) $members->where('status', 'active')->where('joined_on', '<=', $end)
                ->where(fn ($membership) => $membership->whereNull('expires_on')->orWhere('expires_on', '>=', $end))->count(),
            'memberships.new_members' => (float) $members->whereBetween('joined_on', $query->dates())->count(),
            'memberships.dues_collected' => $this->duesCollected($tenantId, $query),
            default => throw new DomainException('Unknown membership measure.'),
        };
    }

    private function duesCollected(string $tenantId, MeasureQuery $query): float
    {
        $invoices = FinInvoice::query()->select('fin_invoices.id')->where('tenant_id', $tenantId)
            ->where('status', '<>', FinInvoice::STATUS_VOID)
            ->whereIn('id', MemRenewal::query()->select('invoice_id')->where('tenant_id', $tenantId));

        return round(FinPaymentAllocation::query()->whereIn('fin_payment_allocations.invoice_id', $invoices)
            ->join('fin_payments', 'fin_payments.id', '=', 'fin_payment_allocations.payment_id')
            ->where('fin_payments.tenant_id', $tenantId)
            ->whereBetween('fin_payments.date', $query->dates())
            ->sum('fin_payment_allocations.amount') / 100, 2);
    }
}
