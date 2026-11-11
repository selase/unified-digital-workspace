<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Demo\Sections;

use App\Models\Tenant;
use App\Modules\Memberships\Models\MemMember;
use App\Modules\Memberships\Models\MemType;

/**
 * The proposed public membership tiers, created only for a tenant that has none. They are real configuration rather than
 * demo data, so removal leaves them in place unless asked; the fees are a proposal to confirm with the client.
 */
final class MembershipTiersSection
{
    private const TIERS = [
        ['Student and Patient Member', 'For students and patients who want to stay informed and connected.', 10_000],
        ['Individual Member', 'For anyone who wants to support thyroid health awareness and vote at our annual meeting.', 20_000],
        ['Corporate Partner', 'For organisations that want to stand with patients and sponsor our screening work.', 200_000],
    ];

    /** @return list<int> The ids of the types created; empty when nothing was needed. */
    public function ensure(Tenant $tenant): array
    {
        if (! MemType::query()->getConnection()->getSchemaBuilder()->hasTable('mem_types')) {
            return [];
        }

        if (MemType::query()->where('tenant_id', $tenant->id)->exists()) {
            return [];
        }

        $ids = [];
        foreach (self::TIERS as [$name, $description, $fee]) {
            $ids[] = MemType::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'description' => $description,
                'fee' => $fee,
                'period_months' => 12,
                'is_public' => true,
            ])->id;
        }

        return $ids;
    }

    /** Deletes the given tiers that nobody has joined and returns how many went. */
    public function removeUnused(Tenant $tenant, array $ids): int
    {
        if ($ids === [] || ! MemType::query()->getConnection()->getSchemaBuilder()->hasTable('mem_types')) {
            return 0;
        }

        return MemType::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $ids)
            ->whereNotIn('id', MemMember::query()->select('type_id'))
            ->delete();
    }
}
