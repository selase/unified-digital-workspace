<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\ResultsFramework\Models\ResultIndicator;
use App\Modules\ResultsFramework\Models\ResultNode;
use App\Modules\ResultsFramework\Models\ResultProgramme;
use App\Modules\ResultsFramework\Models\ResultReading;
use App\Modules\ResultsFramework\Support\Dimensions;
use App\Modules\ResultsFramework\Support\MeasureQuery;
use App\Modules\ResultsFramework\Support\MeasureUnavailable;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Arr;

/**
 * Indicators measure whether a programme is delivering. A manual indicator's figure is its latest reading; the
 * others are worked out live from other modules (so nobody types the same number twice).
 */
final class IndicatorService
{
    private const FIELDS = ['name', 'description', 'definition', 'means_of_verification', 'programme_id', 'result_id', 'unit', 'measure_key', 'measure_filters', 'baseline', 'target', 'starts_on', 'ends_on', 'grant_id'];

    public function __construct(private MeasureRegistry $measures, private BreakdownService $breakdowns, private TargetService $targets) {}

    public function create(string $tenantId, string $name, float $target, CarbonInterface $startsOn, CarbonInterface $endsOn, array $details = [], bool $allowDisabledMeasure = false): ResultIndicator
    {
        $indicator = new ResultIndicator(['tenant_id' => $tenantId, 'status' => 'active', 'unit' => null]);

        return $this->save($indicator, [
            'name' => $name, 'target' => $target, 'starts_on' => $startsOn->toDateString(), 'ends_on' => $endsOn->toDateString(),
        ] + $details, $allowDisabledMeasure);
    }

    public function update(ResultIndicator $indicator, array $details): ResultIndicator
    {
        return $indicator->getConnection()->transaction(function () use ($indicator, $details): ResultIndicator {
            $locked = ResultIndicator::query()->where('tenant_id', $indicator->tenant_id)->whereKey($indicator->id)
                ->lockForUpdate()->firstOrFail();
            $indicator->setRawAttributes($locked->getAttributes(), true);

            return $this->save($indicator, $details);
        });
    }

    public function record(ResultIndicator $indicator, float $value, CarbonInterface $date, ?string $note, ?string $userId, array $breakdowns = []): ResultReading
    {
        if ($indicator->measure_key !== null) {
            throw new DomainException('This indicator is calculated from another module, so readings cannot be entered by hand.');
        }
        if ($indicator->status !== 'active') {
            throw new DomainException('Reactivate the indicator before adding readings.');
        }
        if ($date->gt(today())) {
            throw new DomainException('A reading cannot be dated in the future.');
        }

        $rows = $this->breakdowns->validatedReadingRows($value, $breakdowns);

        return $indicator->getConnection()->transaction(function () use ($indicator, $value, $date, $note, $userId, $rows): ResultReading {
            $reading = $indicator->readings()->create([
                'tenant_id' => $indicator->tenant_id,
                'recorded_on' => $date->toDateString(),
                'value' => $value,
                'note' => $note,
                'recorded_by' => $userId,
            ]);
            foreach ($rows as $row) {
                $reading->breakdowns()->create($row + ['tenant_id' => $indicator->tenant_id]);
            }

            return $reading;
        });
    }

    /** The figure today: the latest reading, or the live number from another module counted within the indicator's dates. */
    public function current(ResultIndicator $indicator): ?float
    {
        if ($indicator->measure_key === null) {
            return (float) ($indicator->readings()->value('value') ?? $indicator->baseline);
        }

        try {
            return $this->measures->value(
                $indicator->tenant ?? Tenant::query()->findOrFail($indicator->tenant_id),
                $indicator->measure_key,
                MeasureQuery::forIndicator($indicator),
            );
        } catch (MeasureUnavailable) {
            return null;
        }
    }

    /**
     * Progress from baseline towards target, how much of the time has passed, and a plain verdict.
     *
     * @return array{current: float|string|null, percent: ?int, time_percent: int, status: string, periods: list<array{start: CarbonInterface, end: CarbonInterface, target: float, achieved: float|string|null, percent: ?int, status: string}>}
     */
    public function progress(ResultIndicator $indicator, User $viewer, bool $includePeriods = true): array
    {
        $current = $this->current($indicator);
        if ($current !== null) {
            $current = Dimensions::protect($current, $this->measures->canSeeExactValue($indicator->measure_key, $viewer));
        }

        $range = $indicator->target - $indicator->baseline;
        $percent = ! is_float($current) ? null : (int) max(0, min(999, round((($current - $indicator->baseline) / $range) * 100)));

        $totalDays = max(1, $indicator->starts_on->diffInDays($indicator->ends_on));
        $timePercent = (int) max(0, min(100, round($indicator->starts_on->diffInDays(today(), false) / $totalDays * 100)));

        $status = match (true) {
            is_string($current) => 'restricted',
            $percent === null => 'unavailable',
            $percent >= 100 => 'achieved',
            today()->gt($indicator->ends_on) => 'missed',
            $timePercent - $percent > 20 => 'behind',
            default => 'on_track',
        };

        return [
            'current' => $current, 'percent' => $percent, 'time_percent' => $timePercent, 'status' => $status,
            'periods' => $includePeriods ? $this->periodProgress($indicator, $viewer) : [],
        ];
    }

    /** @return list<array{start: CarbonInterface, end: CarbonInterface, target: float, achieved: float|string|null, percent: ?int, status: string}> */
    public function periodProgress(ResultIndicator $indicator, User $viewer): array
    {
        $periods = [];
        $decreasing = $indicator->target < $indicator->baseline;
        $maySeeExact = $this->measures->canSeeExactValue($indicator->measure_key, $viewer);

        foreach ($this->targets->forIndicator($indicator) as $target) {
            $start = $target->period_start;
            $end = $target->period_end;
            $achieved = $this->periodFigure($indicator, $start, $end);
            if ($achieved !== null) {
                $achieved = Dimensions::protect($achieved, $maySeeExact);
            }

            $value = $target->target_value;
            $metTarget = is_float($achieved) && ($decreasing ? $achieved <= $value : $achieved >= $value);
            $ratio = match (true) {
                ! is_float($achieved) => null,
                $decreasing && $value < $indicator->baseline => ($indicator->baseline - $achieved) / ($indicator->baseline - $value),
                ! $decreasing && $value > 0 => $achieved / $value,
                ! $decreasing && $value < 0 && $value > $indicator->baseline => ($achieved - $indicator->baseline) / ($value - $indicator->baseline),
                default => $metTarget ? 1.0 : 0.0,
            };
            $percent = $ratio === null ? null : (int) max(0, min($metTarget ? 999 : 99, round($ratio * 100)));

            $status = match (true) {
                is_string($achieved) => 'restricted',
                $metTarget => 'achieved',
                $start->gt(today()) => 'pending',
                $achieved === null => $indicator->measure_key === null ? 'no_reading' : 'unavailable',
                $end->lt(today()) => 'missed',
                default => 'in_progress',
            };

            $periods[] = ['start' => $start, 'end' => $end, 'target' => $value, 'achieved' => $achieved, 'percent' => $percent, 'status' => $status];
        }

        if (array_any($periods, fn (array $period): bool => $period['status'] === 'restricted')) {
            foreach ($periods as $index => $period) {
                $periods[$index] = array_replace($period, ['achieved' => null, 'percent' => null, 'status' => 'restricted']);
            }
        }

        return $periods;
    }

    /** @return list<array{date: string, value: float}> */
    public function history(ResultIndicator $indicator, User $viewer): array
    {
        if ($indicator->measure_key !== null) {
            return $this->periodHistory($this->periodProgress($indicator, $viewer));
        }

        return $indicator->readings()->where('tenant_id', $indicator->tenant_id)->limit(50)->get(['id', 'recorded_on', 'value'])
            ->unique(fn (ResultReading $reading): string => $reading->recorded_on->toDateString())
            ->reverse()->values()
            ->map(fn (ResultReading $reading): array => ['date' => $reading->recorded_on->toDateString(), 'value' => $reading->value])
            ->all();
    }

    /**
     * @param  list<array{start: CarbonInterface, end: CarbonInterface, target: float, achieved: float|string|null, percent: ?int, status: string}>  $periods  Viewer-filtered period progress.
     * @return array<string, mixed>
     */
    public function trend(ResultIndicator $indicator, User $viewer, array $periods): array
    {
        $manual = $indicator->measure_key === null;
        $history = $manual ? $this->history($indicator, $viewer) : $this->periodHistory($periods);
        if ($history === []) {
            return [];
        }

        $readings = array_column($history, 'value', 'date');
        $targets = [];
        foreach ($periods as $period) {
            $targets[$period['end']->toDateString()] = $period['target'];
        }

        $labels = array_values(array_unique([
            $indicator->starts_on->toDateString(), ...array_keys($readings),
            ...($manual ? [] : array_keys($targets)), $indicator->ends_on->toDateString(),
        ]));
        sort($labels);
        $duration = max(1, $indicator->starts_on->diffInDays($indicator->ends_on));

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => $manual ? 'Readings' : 'Period results',
                    'data' => array_map(fn (string $date): ?float => $readings[$date] ?? null, $labels),
                    'borderColor' => '#2563eb', 'yAxisID' => 'y',
                ],
                [
                    'label' => 'Target',
                    'data' => array_map(fn (string $date): ?float => $manual ? $indicator->target : ($targets[$date] ?? null), $labels),
                    'borderColor' => '#15803d', 'borderDash' => [5, 4], 'yAxisID' => 'y',
                ],
                [
                    'label' => 'Time elapsed',
                    'data' => array_map(fn (string $date): int => (int) max(0, min(100, round(
                        $indicator->starts_on->diffInDays(CarbonImmutable::parse($date), false) / $duration * 100
                    ))), $labels),
                    'borderColor' => '#64748b', 'borderDash' => [2, 4], 'yAxisID' => 'elapsed',
                ],
            ],
        ];
    }

    /**
     * Rows for the CSV and PDF report.
     *
     * @return list<list<string|int|float|null>>
     */
    public function reportRows(string $tenantId, User $viewer): array
    {
        $rows = [['Indicator report', 'As at '.today()->toDateString()], ['Indicator', 'Programme', 'Unit', 'Baseline', 'Target', 'Current', 'Progress %', 'Period', 'Status', 'Breakdowns']];
        $labels = ['achieved' => 'Achieved', 'missed' => 'Missed', 'behind' => 'Behind', 'on_track' => 'On track', 'unavailable' => 'Unavailable', 'restricted' => 'Restricted'];

        foreach (ResultIndicator::query()->where('tenant_id', $tenantId)->where('status', 'active')->with(['tenant', 'programme' => fn ($query) => $query->where('tenant_id', $tenantId)])->orderBy('programme_id')->orderBy('name')->get() as $indicator) {
            $progress = $this->progress($indicator, $viewer, includePeriods: false);
            $breakdownLines = [];
            foreach ($this->breakdowns->allForIndicator($indicator, $viewer) as $dimension => $buckets) {
                if ($buckets === null) {
                    $breakdownLines[] = Dimensions::LABELS[$dimension].': '.BreakdownService::HIDDEN_NOTE;

                    continue;
                }

                foreach ($buckets as $bucket => $value) {
                    $breakdownLines[] = Dimensions::LABELS[$dimension].' / '.Dimensions::bucketLabel($dimension, (string) $bucket).': '.$value;
                }
            }
            $rows[] = [$indicator->name, $indicator->programmeName(), $indicator->unit, $indicator->baseline, $indicator->target, $progress['current'] ?? 'Unavailable', $progress['percent'], $indicator->starts_on->toDateString().' to '.$indicator->ends_on->toDateString(), $labels[$progress['status']], implode("\n", $breakdownLines)];
        }

        return $rows;
    }

    /**
     * @param  list<array{end: CarbonInterface, achieved: float|string|null}>  $periods
     * @return list<array{date: string, value: float}>
     */
    private function periodHistory(array $periods): array
    {
        $history = [];
        foreach ($periods as $period) {
            if (is_float($period['achieved'])) {
                $history[] = ['date' => $period['end']->toDateString(), 'value' => $period['achieved']];
            }
        }

        return $history;
    }

    private function periodFigure(ResultIndicator $indicator, CarbonInterface $start, CarbonInterface $end): ?float
    {
        if ($indicator->measure_key === null) {
            $reading = $indicator->readings()->where('tenant_id', $indicator->tenant_id)
                ->whereBetween('recorded_on', [$start->toDateString(), $end->toDateString()])->value('value');

            return $reading === null ? null : (float) $reading;
        }

        try {
            return $this->measures->value(
                $indicator->tenant ?? Tenant::query()->findOrFail($indicator->tenant_id),
                $indicator->measure_key,
                new MeasureQuery($start, $end, $indicator->measure_filters ?? []),
            );
        } catch (MeasureUnavailable) {
            return null;
        }
    }

    private function save(ResultIndicator $indicator, array $details, bool $allowDisabledMeasure = false): ResultIndicator
    {
        $candidate = clone $indicator;
        $candidate->fill(Arr::only($details, self::FIELDS));
        $candidate->baseline = $candidate->baseline ?? 0.0;
        if (! $candidate->starts_on || ! $candidate->ends_on || CarbonImmutable::parse($candidate->ends_on)->lt($candidate->starts_on)) {
            throw new DomainException('The indicator must end after it starts.');
        }
        if ($indicator->exists && $candidate->isDirty(['starts_on', 'ends_on'])
            && $indicator->targets()->where('tenant_id', $indicator->tenant_id)
                ->where(fn ($query) => $query->where('period_start', '<', $candidate->starts_on->toDateString())
                    ->orWhere('period_end', '>', $candidate->ends_on->toDateString()))->exists()) {
            throw new DomainException('The indicator dates must include its existing period targets.');
        }
        $filters = $candidate->measure_filters ?? [];
        if ($candidate->measure_key === null && $filters !== []) {
            throw new DomainException('Choose a live measure before adding filters.');
        }
        ksort($filters);
        $candidate->measure_filters = $filters ?: null;
        $previousFilters = $indicator->measure_filters ?? [];
        ksort($previousFilters);
        $changed = $candidate->measure_key !== $indicator->measure_key || $filters !== $previousFilters;
        if ($changed && $indicator->exists && ! ($details['confirm_source_change'] ?? false) && $indicator->readings()->exists()) {
            throw new DomainException('Confirm the change of source because this indicator already has readings.');
        }

        $definition = null;
        if ($candidate->measure_key !== null && (! $indicator->exists || $changed)) {
            $definition = $this->measures->validateSelection(
                Tenant::query()->findOrFail($candidate->tenant_id),
                $candidate->measure_key,
                $filters,
                $allowDisabledMeasure && $filters === [],
            );
        }
        $candidate->unit = $candidate->unit ?: ($definition?->unit ?? $indicator->unit ?? 'people');
        if ($candidate->target === $candidate->baseline) {
            throw new DomainException('The target must be different from the starting figure.');
        }

        $programmeId = $candidate->programme_id;
        if ($programmeId !== null && ! ResultProgramme::query()->where('tenant_id', $candidate->tenant_id)->whereKey($programmeId)->exists()) {
            throw new DomainException('Choose a programme in your organisation.');
        }

        if ($candidate->result_id !== null && (! $programmeId || ! ResultNode::query()->where('tenant_id', $candidate->tenant_id)
            ->where('programme_id', $programmeId)->whereKey($candidate->result_id)->exists())) {
            throw new DomainException('Choose a result from this indicator’s programme.');
        }

        $fields = Arr::only($candidate->getAttributes(), self::FIELDS);
        $fields['measure_filters'] = $candidate->measure_filters;
        $indicator->fill($fields);
        $indicator->save();
        $indicator->unsetRelation('programme')->unsetRelation('node');

        return $indicator;
    }
}
