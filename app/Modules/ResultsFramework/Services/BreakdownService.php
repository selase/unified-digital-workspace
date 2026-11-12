<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\ResultsFramework\Models\ResultIndicator;
use App\Modules\ResultsFramework\Support\Dimensions;
use App\Modules\ResultsFramework\Support\MeasureQuery;
use App\Modules\ResultsFramework\Support\MeasureUnavailable;
use DomainException;

final class BreakdownService
{
    public const HIDDEN_NOTE = 'Hidden to protect small groups';

    public function __construct(private MeasureRegistry $measures) {}

    /** @return array<string, float> */
    public function forIndicator(ResultIndicator $indicator, User $viewer, string $dimension): array
    {
        return $this->forDimensions($indicator, $viewer, [$dimension])[$dimension] ?? [];
    }

    /** @return array<string, array<string, float>|null> */
    public function allForIndicator(ResultIndicator $indicator, User $viewer): array
    {
        return $this->forDimensions($indicator, $viewer, array_keys(Dimensions::LABELS));
    }

    /**
     * @param  array<string, list<array{bucket?: string|null, value?: float|int|string|null}>>  $input
     * @return list<array{dimension: string, bucket: string, value: float}>
     */
    public function validatedReadingRows(float $total, array $input): array
    {
        $rows = [];

        foreach ($input as $dimension => $entries) {
            if (! isset(Dimensions::LABELS[$dimension])) {
                throw new DomainException('Choose a supported breakdown dimension.');
            }

            $values = [];
            foreach ($entries as $entry) {
                $bucket = mb_trim((string) ($entry['bucket'] ?? ''));
                $value = $entry['value'] ?? null;
                if ($value === null || $value === '') {
                    if ($dimension === 'location' && $bucket !== '') {
                        throw new DomainException('Enter a figure for each location.');
                    }

                    continue;
                }

                if ($bucket === '' || mb_strlen($bucket) > 255
                    || ($dimension !== 'location' && ! isset(Dimensions::FIXED_BUCKETS[$dimension][$bucket]))) {
                    throw new DomainException('Choose a valid breakdown bucket.');
                }
                if (array_key_exists($bucket, $values)) {
                    throw new DomainException('Enter each breakdown bucket once.');
                }
                if (! is_numeric($value) || ! is_finite((float) $value) || $value < 0 || $value > 999999999999.99
                    || round((float) $value, 2) !== (float) $value) {
                    throw new DomainException('Enter non-negative breakdown figures with up to two decimal places.');
                }

                $values[$bucket] = (float) $value;
            }

            if ($values !== [] && (round(array_sum($values), 2) !== $total)) {
                throw new DomainException(Dimensions::LABELS[$dimension].' figures must add up to the reading.');
            }

            foreach ($values as $bucket => $value) {
                $rows[] = ['dimension' => $dimension, 'bucket' => (string) $bucket, 'value' => $value];
            }
        }

        return $rows;
    }

    /**
     * @param  list<string>  $dimensions
     * @return array<string, array<string, float>|null>
     */
    private function forDimensions(ResultIndicator $indicator, User $viewer, array $dimensions): array
    {
        $result = $this->collectBuckets($indicator, $dimensions);
        if (! $viewer->can('beneficiaries.view')) {
            foreach ($result as $dimension => $buckets) {
                if (array_any($buckets, fn (float $value): bool => $value > 0 && $value < 3)) {
                    $result[$dimension] = null;
                }
            }
        }

        return $result;
    }

    /**
     * @param  list<string>  $dimensions
     * @return array<string, array<string, float>>
     */
    private function collectBuckets(ResultIndicator $indicator, array $dimensions): array
    {
        $result = [];

        if ($indicator->measure_key === null) {
            $reading = $indicator->readings()->where('tenant_id', $indicator->tenant_id)->first();
            if ($reading !== null) {
                foreach ($reading->breakdowns()->where('tenant_id', $indicator->tenant_id)->whereIn('dimension', $dimensions)->orderBy('id')->get() as $row) {
                    $result[$row->dimension][$row->bucket] = $row->value;
                }
            }

            return $result;
        }

        $tenant = $indicator->tenant ?? Tenant::query()->findOrFail($indicator->tenant_id);
        try {
            $supported = $this->measures->dimensions($tenant, $indicator->measure_key);
            $query = MeasureQuery::forIndicator($indicator);
            foreach (array_intersect($dimensions, $supported) as $dimension) {
                $buckets = $this->measures->breakdown($tenant, $indicator->measure_key, $dimension, $query);
                if ($buckets !== []) {
                    $result[$dimension] = $buckets;
                }
            }
        } catch (MeasureUnavailable) {
            return [];
        }

        return $result;
    }
}
