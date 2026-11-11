<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Demo\Sections;

use App\Models\Tenant;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Memberships\Models\MemMember;
use App\Modules\Memberships\Models\MemRenewal;
use App\Modules\Memberships\Models\MemType;
use App\Modules\Memberships\Services\MembershipService;
use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoSection;
use App\Modules\SiteThyroidGhanaFoundation\Demo\RowRemover;
use Carbon\CarbonImmutable;
use DomainException;

/** Members across the tenant's existing tiers: paid, one waiting to pay and one due to renew. */
final class MembershipsSection implements DemoSection
{
    public function label(): string
    {
        return 'Memberships';
    }

    public function seed(Tenant $tenant): void
    {
        $tenantId = (string) $tenant->id;
        $types = MemType::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('fee')->get()->values();
        if ($types->isEmpty()) {
            throw new DomainException('Create membership types before seeding demo members.');
        }

        $entry = $types->first();
        $standard = $types->get(intdiv($types->count(), 2));
        $top = $types->last();
        $service = app(MembershipService::class);
        $payments = app(PaymentService::class);

        $pay = function (MemMember $member) use ($payments, $tenantId, $service): void {
            $renewal = MemRenewal::query()->where('member_id', $member->id)->where('status', 'billed')->with('invoice.contact')->firstOrFail();
            $payments->record($tenantId, $renewal->invoice->contact, $renewal->invoice->total, today(), [$renewal->invoice->id => $renewal->invoice->total], ['method' => 'bank_transfer']);
            $service->applyPayments($tenantId);
        };

        $paid = [
            ['Demo: Akua Frimpong', $standard],
            ['Demo: Kwabena Ofori', $standard],
            ['Demo: Esi Nyarko', $entry],
            ['Demo: Dr. Yaw Asare', $standard],
            ['Demo: Mensah Agyei Pharmacy', $top],
        ];

        foreach ($paid as $index => [$name, $type]) {
            $pay($service->join($tenantId, $type, $name, ['email' => 'member'.($index + 1).'@example.test']));
        }

        $service->join($tenantId, $entry, 'Demo: Efua Tetteh', ['email' => 'member6@example.test']);

        $renewing = $service->join($tenantId, $entry, 'Demo: Naana Boadu', ['email' => 'member7@example.test']);
        $pay($renewing);
        $renewing->forceFill(['expires_on' => today()->addDays(MembershipService::RENEWAL_LEAD_DAYS + 15)])->save();
    }

    public function remove(Tenant $tenant, CarbonImmutable $since): int
    {
        $remover = new RowRemover;

        return $remover->children('mem_renewals', 'member_id', 'mem_members', $tenant, $since, 'name') + $remover->rows('mem_members', $tenant, $since, 'name');
    }
}
