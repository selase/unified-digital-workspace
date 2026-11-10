<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Services;

use App\Models\Tenant;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Models\FinInvoice;
use App\Modules\Finance\Services\ChartOfAccounts;
use App\Modules\Finance\Services\InvoiceService;
use App\Modules\Finance\Services\NumberSequence;
use App\Modules\Memberships\Models\MemMember;
use App\Modules\Memberships\Models\MemRenewal;
use App\Modules\Memberships\Models\MemType;
use App\Services\Alerts\AlertDispatcher;
use App\Services\Alerts\AlertEvent;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Members, their dues and renewals. Dues are ordinary Finance invoices, so receivables, payment links,
 * reconciliation and the income report all work without a second set of books. A member becomes active
 * when the dues invoice is paid, and lapses when the paid period ends.
 */
final class MembershipService
{
    public const INCOME_ACCOUNT = '4230';

    /** How many days before a membership ends the renewal invoice is raised. */
    public const RENEWAL_LEAD_DAYS = 30;

    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly NumberSequence $numbers,
        private readonly ChartOfAccounts $accounts,
        private readonly AlertDispatcher $alerts,
    ) {}

    /**
     * Adds a member. A free type is active straight away; a paid type is invoiced and waits for payment.
     *
     * @param  array{email?: string|null, phone?: string|null, notes?: string|null}  $details
     */
    public function join(string $tenantId, MemType $type, string $name, array $details = [], ?string $userId = null): MemMember
    {
        if (! $type->is_active || $type->tenant_id !== $tenantId) {
            throw new DomainException('That membership type is not available.');
        }

        $email = isset($details['email']) && $details['email'] !== '' ? mb_strtolower((string) $details['email']) : null;
        if ($email !== null && MemMember::query()->where('tenant_id', $tenantId)->where('email', $email)->whereIn('status', ['pending', 'active', 'expired'])->exists()) {
            throw new DomainException('A member with this email address already exists.');
        }

        return DB::connection('tenant')->transaction(function () use ($tenantId, $type, $name, $email, $details, $userId): MemMember {
            $member = MemMember::query()->create([
                'tenant_id' => $tenantId,
                'number' => $this->numbers->next($tenantId, 'member', 'MEM-'),
                'type_id' => $type->id,
                'contact_id' => $this->contactFor($tenantId, $name, $email, $details['phone'] ?? null)->id,
                'name' => $name,
                'email' => $email,
                'phone' => $details['phone'] ?? null,
                'notes' => $details['notes'] ?? null,
                'status' => 'pending',
            ]);

            if ($type->fee <= 0) {
                $this->activate($member, today());
            } else {
                $this->billDues($member, $userId);
            }

            return $member->refresh();
        });
    }

    /** Raises the dues invoice for the next period. A member can only have one unpaid dues invoice at a time. */
    public function billDues(MemMember $member, ?string $userId = null): MemRenewal
    {
        if (in_array($member->status, ['cancelled'], true)) {
            throw new DomainException('A cancelled membership cannot be billed. Reinstate it first.');
        }
        $type = $member->type;
        if ($type->fee <= 0) {
            throw new DomainException('This membership type has no fee to bill.');
        }
        if ($member->renewals()->where('status', MemRenewal::BILLED)->exists()) {
            throw new DomainException('This member already has an unpaid dues invoice.');
        }

        [$start, $end] = $this->nextPeriod($member, $type);

        return DB::connection('tenant')->transaction(function () use ($member, $type, $start, $end, $userId): MemRenewal {
            $invoice = $this->invoices->saveDraft($member->tenant_id, $member->contact, [
                'issue_date' => today()->toDateString(),
                'notes' => 'Membership '.$member->number.', '.$start->toInstitutionalDate().' to '.$end->toInstitutionalDate().'.',
            ], [[
                'description' => $type->name.' membership dues, '.$start->toInstitutionalDate().' to '.$end->toInstitutionalDate(),
                'quantity' => 1,
                'unit_price' => $type->fee,
                'account_id' => $this->incomeAccount($member->tenant_id)->id,
            ]], null, $userId);
            $invoice = $this->invoices->send($invoice, $userId);

            return MemRenewal::query()->create([
                'tenant_id' => $member->tenant_id,
                'member_id' => $member->id,
                'type_id' => $type->id,
                'invoice_id' => $invoice->id,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'amount' => $invoice->total,
                'status' => MemRenewal::BILLED,
            ]);
        });
    }

    /** For honorary or complimentary members: activate for one period without an invoice. */
    public function grantComplimentary(MemMember $member): MemMember
    {
        if ($member->status === 'cancelled') {
            throw new DomainException('A cancelled membership cannot be activated. Reinstate it first.');
        }
        if ($member->renewals()->where('status', MemRenewal::BILLED)->exists()) {
            throw new DomainException('Void or collect the unpaid dues invoice first.');
        }

        [$start] = $this->nextPeriod($member, $member->type);
        $this->activate($member, $start);

        return $member->refresh();
    }

    public function changeType(MemMember $member, MemType $type): MemMember
    {
        if ($type->tenant_id !== $member->tenant_id || ! $type->is_active) {
            throw new DomainException('That membership type is not available.');
        }

        $member->forceFill(['type_id' => $type->id])->save();

        return $member;
    }

    /** Ends the membership and voids any dues invoice that has not been paid. */
    public function cancel(MemMember $member, ?string $userId = null): MemMember
    {
        DB::connection('tenant')->transaction(function () use ($member, $userId): void {
            foreach ($member->renewals()->where('status', MemRenewal::BILLED)->with('invoice')->get() as $renewal) {
                $this->voidRenewal($renewal, $userId);
            }
            $member->forceFill(['status' => 'cancelled'])->save();
        });

        return $member;
    }

    public function reinstate(MemMember $member): MemMember
    {
        if ($member->status !== 'cancelled') {
            throw new DomainException('Only a cancelled membership can be reinstated.');
        }

        $member->forceFill(['status' => $member->expires_on !== null && $member->expires_on->gte(today()) ? 'active' : ($member->joined_on === null ? 'pending' : 'expired')])->save();

        return $member;
    }

    /**
     * Turns paid dues invoices into membership. Safe to run any time; it only looks at unsettled renewals.
     * Returns how many members were activated or extended.
     */
    public function applyPayments(string $tenantId): int
    {
        $applied = 0;

        $billed = MemRenewal::query()->where('tenant_id', $tenantId)->where('status', MemRenewal::BILLED)->with(['invoice', 'member'])->orderBy('period_start')->get();

        foreach ($billed as $renewal) {
            $invoice = $renewal->invoice;
            if (! $invoice instanceof FinInvoice || $invoice->status === FinInvoice::STATUS_VOID) {
                $renewal->forceFill(['status' => MemRenewal::VOID])->save();

                continue;
            }
            if ($invoice->status !== FinInvoice::STATUS_PAID) {
                continue;
            }

            DB::connection('tenant')->transaction(function () use ($renewal): void {
                $renewal->forceFill(['status' => MemRenewal::PAID])->save();
                $member = $renewal->member;
                if ($member->status !== 'cancelled') {
                    $this->activate($member, $renewal->period_start, $renewal->period_end);
                }
            });
            $applied++;
        }

        return $applied;
    }

    /** Marks active members whose paid period has ended as expired. */
    public function expireLapsed(string $tenantId, ?CarbonInterface $today = null): int
    {
        return MemMember::query()->where('tenant_id', $tenantId)->where('status', 'active')
            ->whereDate('expires_on', '<', ($today ?? today())->toDateString())
            ->update(['status' => 'expired']);
    }

    /**
     * Raises renewal invoices for active members whose period ends soon, for types set to renew automatically.
     * Returns how many invoices were raised.
     */
    public function invoiceUpcomingRenewals(Tenant $tenant, ?CarbonInterface $today = null): int
    {
        $today = CarbonImmutable::instance($today ?? today());
        $raised = 0;

        $due = MemMember::query()->where('tenant_id', $tenant->id)->where('status', 'active')
            ->whereDate('expires_on', '<=', $today->addDays(self::RENEWAL_LEAD_DAYS)->toDateString())
            ->whereHas('type', fn ($query) => $query->where('auto_invoice', true)->where('fee', '>', 0)->where('is_active', true))
            ->whereDoesntHave('renewals', fn ($query) => $query->where('status', MemRenewal::BILLED))
            ->with(['type', 'contact'])->get();

        foreach ($due as $member) {
            $renewal = $this->billDues($member);
            $raised++;
            $this->alerts->dispatchSafely(
                $tenant,
                AlertEvent::MEMBERSHIP_RENEWAL,
                'Membership renewal invoiced',
                $member->name.' ('.$member->number.') is due to renew on '.$member->expires_on->toDateString().'. Invoice '.$renewal->invoice?->number.' has been raised.',
                'membership-renewal-'.$renewal->id,
                route('memberships.members.show', $member->uuid, false),
            );
        }

        return $raised;
    }

    /**
     * Counts and dues for the overview.
     *
     * @return array{by_status: array<string, int>, by_type: list<array{type: MemType, active: int}>, expiring: int, collected: int, outstanding: int}
     */
    public function summary(string $tenantId, CarbonInterface $yearStart): array
    {
        $byStatus = MemMember::query()->where('tenant_id', $tenantId)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($count): int => (int) $count)->all();

        $types = MemType::query()->where('tenant_id', $tenantId)->orderBy('name')->get();
        $activeByType = MemMember::query()->where('tenant_id', $tenantId)->where('status', 'active')->selectRaw('type_id, count(*) as total')->groupBy('type_id')->pluck('total', 'type_id');

        return [
            'by_status' => $byStatus,
            'by_type' => $types->map(fn (MemType $type): array => ['type' => $type, 'active' => (int) ($activeByType[$type->id] ?? 0)])->all(),
            'expiring' => MemMember::query()->where('tenant_id', $tenantId)->where('status', 'active')->whereDate('expires_on', '<=', today()->addDays(60)->toDateString())->count(),
            'collected' => (int) MemRenewal::query()->where('tenant_id', $tenantId)->where('status', MemRenewal::PAID)->whereDate('created_at', '>=', $yearStart->toDateString())->sum('amount'),
            'outstanding' => (int) MemRenewal::query()->where('tenant_id', $tenantId)->where('status', MemRenewal::BILLED)->sum('amount'),
        ];
    }

    private function activate(MemMember $member, CarbonInterface $start, ?CarbonInterface $end = null): void
    {
        $end ??= CarbonImmutable::instance($start)->addMonthsNoOverflow($member->type->period_months)->subDay();

        $member->forceFill([
            'status' => 'active',
            'joined_on' => $member->joined_on ?? $start->toDateString(),
            'expires_on' => $end->toDateString(),
        ])->save();
    }

    /**
     * Renewing while still active continues from the day after the current period; a new or lapsed member starts today.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function nextPeriod(MemMember $member, MemType $type): array
    {
        $start = $member->status === 'active' && $member->expires_on !== null && $member->expires_on->gte(today())
            ? CarbonImmutable::instance($member->expires_on)->addDay()
            : CarbonImmutable::today();

        return [$start, $start->addMonthsNoOverflow($type->period_months)->subDay()];
    }

    private function voidRenewal(MemRenewal $renewal, ?string $userId): void
    {
        $invoice = $renewal->invoice;
        if ($invoice instanceof FinInvoice && $invoice->status !== FinInvoice::STATUS_VOID) {
            if ($invoice->amount_paid > 0) {
                return;
            }
            $this->invoices->void($invoice, $userId);
        }
        $renewal->forceFill(['status' => MemRenewal::VOID])->save();
    }

    private function contactFor(string $tenantId, string $name, ?string $email, ?string $phone): FinContact
    {
        if ($email !== null) {
            $existing = FinContact::query()->where('tenant_id', $tenantId)->where('email', $email)->first();
            if ($existing instanceof FinContact) {
                return $existing;
            }
        }

        return FinContact::query()->create([
            'tenant_id' => $tenantId, 'name' => $name, 'email' => $email, 'phone' => $phone, 'is_customer' => true, 'is_vendor' => false, 'is_active' => true,
        ]);
    }

    private function incomeAccount(string $tenantId): FinAccount
    {
        $this->accounts->ensureSeeded($tenantId);

        return FinAccount::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => self::INCOME_ACCOUNT],
            ['name' => 'Membership dues', 'type' => 'income'],
        );
    }
}
