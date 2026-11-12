<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Support;

use Illuminate\Database\Connection;
use RuntimeException;

final class RollbackPreflight
{
    public const LEGACY_MEASURES = [
        'beneficiaries_reached' => 'beneficiaries.people_reached',
        'volunteer_hours' => 'volunteers.hours_approved',
        'donations_total' => 'donations.amount_raised',
    ];

    /** @return list<string> */
    public function tenantBlockers(Connection $db): array
    {
        $schema = $db->getSchemaBuilder();
        $blockers = [];
        foreach (['rf_variance_notes' => 'Variance notes', 'rf_targets' => 'Period targets', 'rf_reading_breakdowns' => 'Reading breakdowns', 'rf_results' => 'Results chains'] as $table => $label) {
            if ($schema->hasTable($table) && $db->table($table)->exists()) {
                $blockers[] = $label;
            }
        }
        foreach (['indicators', 'readings'] as $suffix) {
            if ($schema->hasTable('rf_'.$suffix) && $schema->hasTable('pr_'.$suffix)) {
                $blockers[] = 'Both results table names exist for '.$suffix;
            }
        }
        if ($this->hasUnrepresentableMeasures($db)) {
            $blockers[] = 'Unrepresentable indicator measures or filters';
        }
        if ($schema->hasColumns('rf_indicators', ['definition', 'means_of_verification'])
            && $db->table('rf_indicators')->whereNotNull('definition')->orWhereNotNull('means_of_verification')->exists()) {
            $blockers[] = 'Indicator definitions or means of verification';
        }
        if ($schema->hasTable('rf_programmes') && $schema->hasColumn('rf_indicators', 'programme_id')) {
            if ($db->table('rf_programmes')->where('status', '<>', 'planning')->orWhereNotNull('description')
                ->orWhereNotNull('starts_on')->orWhereNotNull('ends_on')->orWhereNotNull('owner_id')->orWhereNotNull('grant_id')
                ->orWhereNotExists(fn ($query) => $query->selectRaw('1')->from('rf_indicators')
                    ->whereColumn('rf_indicators.programme_id', 'rf_programmes.id')->whereColumn('rf_indicators.tenant_id', 'rf_programmes.tenant_id'))->exists()) {
                $blockers[] = 'Programme metadata or standalone programmes';
            }
            if ($db->table('rf_indicators')->join('rf_programmes', 'rf_programmes.id', '=', 'rf_indicators.programme_id')
                ->whereColumn('rf_indicators.tenant_id', '<>', 'rf_programmes.tenant_id')->exists()) {
                $blockers[] = 'Cross-tenant programme links';
            }
        }

        return $blockers;
    }

    public function hasUnrepresentableMeasures(Connection $db): bool
    {
        return $db->getSchemaBuilder()->hasColumns('rf_indicators', ['measure_key', 'measure_filters'])
            && $db->table('rf_indicators')->whereNotIn('measure_key', array_values(self::LEGACY_MEASURES))
                ->orWhereNotNull('measure_filters')->exists();
    }

    /** @return list<string> */
    public function landlordBlockers(Connection $db): array
    {
        $blockers = [];
        foreach ([
            ['tenant_modules', 'module_slug', 'results-framework', 'programme-results'],
            ['tenant_features', 'feature_key', 'module-results-framework', 'module-programme-results'],
            ['tenant_features', 'feature_key', 'results-framework.manage', 'programme-results.manage'],
        ] as [$table, $column, $current, $legacy]) {
            if ($db->table($table.' as current')->where('current.'.$column, $current)
                ->whereExists(fn ($query) => $query->selectRaw('1')->from($table.' as legacy')
                    ->where('legacy.'.$column, $legacy)->whereColumn('legacy.tenant_id', 'current.tenant_id'))->exists()) {
                $blockers[] = 'Landlord collision for '.$legacy;
            }
        }
        foreach (['module-', ''] as $prefix) {
            $suffix = $prefix === '' ? '.manage' : '';
            if ($db->table('features')->whereIn('slug', [$prefix.'results-framework'.$suffix, $prefix.'programme-results'.$suffix])->distinct()->count('slug') === 2) {
                $blockers[] = 'Feature catalogue collision for '.$prefix.'programme-results'.$suffix;
            }
        }

        return $blockers;
    }

    public function assertTenantCanRollback(Connection $db): void
    {
        $this->assertClear($this->tenantBlockers($db));
    }

    public function assertLandlordCanRollback(Connection $db): void
    {
        $this->assertClear($this->landlordBlockers($db));
    }

    /** @param list<string> $blockers */
    private function assertClear(array $blockers): void
    {
        if ($blockers !== []) {
            throw new RuntimeException('Results Framework rollback refused: '.implode(', ', $blockers));
        }
    }
}
