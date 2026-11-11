<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Demo;

use App\Models\Tenant;
use App\Modules\CmsCore\Models\Setting;
use App\Modules\Finance\Models\FinSequence;
use Carbon\CarbonImmutable;

/** Remembers when the demo seed began and the finance number counters at that moment, so removal can be exact. */
final class DemoManifest
{
    private const GROUP = 'tgf-demo';

    private const KEY = 'manifest';

    public function begin(Tenant $tenant): CarbonImmutable
    {
        $since = CarbonImmutable::now()->startOfSecond();
        $this->save($tenant, ['started_at' => $since->toIso8601String(), 'counters' => $this->currentCounters($tenant), 'counts' => [], 'tier_ids' => $this->proposedTierIds($tenant)]);

        return $since;
    }

    public function startedAt(Tenant $tenant): ?CarbonImmutable
    {
        $value = $this->load($tenant)['started_at'] ?? null;

        return is_string($value) ? CarbonImmutable::parse($value) : null;
    }

    /** @return array<string, int> */
    public function counters(Tenant $tenant): array
    {
        return $this->load($tenant)['counters'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $counts
     * @param  array<string, int>  $financeRows
     */
    public function finish(Tenant $tenant, array $counts, array $financeRows = []): void
    {
        $this->save($tenant, [...$this->load($tenant), 'counts' => $counts, 'finance_rows' => $financeRows]);
    }

    /** @return array<string, int> */
    public function financeRows(Tenant $tenant): array
    {
        return $this->load($tenant)['finance_rows'] ?? [];
    }

    public function restoreCounters(Tenant $tenant): void
    {
        $saved = $this->counters($tenant);
        if (! FinSequence::query()->getConnection()->getSchemaBuilder()->hasTable('fin_sequences')) {
            return;
        }

        foreach (FinSequence::query()->where('tenant_id', $tenant->id)->get() as $sequence) {
            if (array_key_exists($sequence->key, $saved)) {
                $sequence->forceFill(['last_number' => $saved[$sequence->key]])->save();
            } else {
                $sequence->delete();
            }
        }
    }

    /** Forgets the seed but keeps the proposed tier ids, so the tiers can still be removed later. */
    public function clear(Tenant $tenant): void
    {
        $tiers = $this->proposedTierIds($tenant);
        $tiers === [] ? $this->delete($tenant) : $this->save($tenant, ['tier_ids' => $tiers]);
    }

    public function forgetTierIds(Tenant $tenant): void
    {
        $manifest = $this->load($tenant);

        isset($manifest['started_at']) ? $this->save($tenant, [...$manifest, 'tier_ids' => []]) : $this->delete($tenant);
    }

    /** @return list<int> */
    public function proposedTierIds(Tenant $tenant): array
    {
        return array_map('intval', $this->load($tenant)['tier_ids'] ?? []);
    }

    /** @param  list<int>  $ids */
    public function rememberTierIds(Tenant $tenant, array $ids): void
    {
        $this->save($tenant, [...$this->load($tenant), 'tier_ids' => array_values(array_unique([...$this->proposedTierIds($tenant), ...$ids]))]);
    }

    /** @return array<string, int> */
    private function currentCounters(Tenant $tenant): array
    {
        if (! FinSequence::query()->getConnection()->getSchemaBuilder()->hasTable('fin_sequences')) {
            return [];
        }

        return FinSequence::query()->where('tenant_id', $tenant->id)->pluck('last_number', 'key')->map(fn ($n): int => (int) $n)->all();
    }

    /** @return array<string, mixed> */
    private function load(Tenant $tenant): array
    {
        return Setting::query()->where('tenant_id', $tenant->id)->where('group', self::GROUP)->where('key', self::KEY)->first()?->value ?? [];
    }

    private function delete(Tenant $tenant): void
    {
        Setting::query()->where('tenant_id', $tenant->id)->where('group', self::GROUP)->where('key', self::KEY)->delete();
    }

    /** @param  array<string, mixed>  $value */
    private function save(Tenant $tenant, array $value): void
    {
        Setting::query()->updateOrCreate(['tenant_id' => $tenant->id, 'group' => self::GROUP, 'key' => self::KEY], ['value' => $value]);
    }
}
