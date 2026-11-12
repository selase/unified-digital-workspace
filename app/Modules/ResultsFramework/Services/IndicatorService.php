<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Services;

use App\Modules\ResultsFramework\Models\ResultIndicator;
use App\Modules\ResultsFramework\Models\ResultNode;
use App\Modules\ResultsFramework\Models\ResultProgramme;
use App\Modules\ResultsFramework\Models\ResultReading;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indicators measure whether a programme is delivering. A manual indicator's figure is its latest reading; the
 * others are worked out live from other modules (so nobody types the same number twice).
 */
final class IndicatorService
{
    private const FIELDS = ['name', 'description', 'definition', 'means_of_verification', 'programme_id', 'result_id', 'unit', 'source', 'baseline', 'target', 'starts_on', 'ends_on', 'grant_id'];

    public function create(string $tenantId, string $name, float $target, CarbonInterface $startsOn, CarbonInterface $endsOn, array $details = []): ResultIndicator
    {
        $indicator = new ResultIndicator(['tenant_id' => $tenantId, 'status' => 'active']);

        return $this->save($indicator, [
            'name' => $name, 'target' => $target, 'starts_on' => $startsOn->toDateString(), 'ends_on' => $endsOn->toDateString(),
        ] + $details);
    }

    public function update(ResultIndicator $indicator, array $details): ResultIndicator
    {
        if (isset($details['source']) && $details['source'] !== $indicator->source
            && ! ($details['confirm_source_change'] ?? false) && $indicator->readings()->exists()) {
            throw new DomainException('Confirm the change of source because this indicator already has readings.');
        }

        return $this->save($indicator, $details);
    }

    public function record(ResultIndicator $indicator, float $value, CarbonInterface $date, ?string $note, ?string $userId): ResultReading
    {
        if ($indicator->source !== 'manual') {
            throw new DomainException('This indicator is calculated from another module, so readings cannot be entered by hand.');
        }
        if ($indicator->status !== 'active') {
            throw new DomainException('Reactivate the indicator before adding readings.');
        }
        if ($date->gt(today())) {
            throw new DomainException('A reading cannot be dated in the future.');
        }

        return ResultReading::query()->create([
            'tenant_id' => $indicator->tenant_id,
            'indicator_id' => $indicator->id,
            'recorded_on' => $date->toDateString(),
            'value' => $value,
            'note' => $note,
            'recorded_by' => $userId,
        ]);
    }

    /** The figure today: the latest reading, or the live number from another module counted within the indicator's dates. */
    public function current(ResultIndicator $indicator): float
    {
        $from = $indicator->starts_on->toDateString();
        $to = min($indicator->ends_on->toDateString(), today()->toDateString());

        return match ($indicator->source) {
            'beneficiaries_reached' => Schema::connection('tenant')->hasTable('ben_services')
                ? (float) DB::connection('tenant')->table('ben_services')->where('tenant_id', $indicator->tenant_id)->whereDate('delivered_on', '>=', $from)->whereDate('delivered_on', '<=', $to)->distinct()->count('beneficiary_id')
                : 0.0,
            'volunteer_hours' => Schema::connection('tenant')->hasTable('vol_hours')
                ? round(((int) DB::connection('tenant')->table('vol_hours')->where('tenant_id', $indicator->tenant_id)->where('status', 'approved')->whereDate('worked_on', '>=', $from)->whereDate('worked_on', '<=', $to)->sum('minutes')) / 60, 2)
                : 0.0,
            'donations_total' => Schema::connection('tenant')->hasTable('don_donations')
                ? round(((int) DB::connection('tenant')->table('don_donations')->where('tenant_id', $indicator->tenant_id)->whereNull('voided_at')->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->sum('amount')) / 100, 2)
                : 0.0,
            default => (float) ($indicator->readings()->value('value') ?? $indicator->baseline),
        };
    }

    /**
     * Progress from baseline towards target, how much of the time has passed, and a plain verdict.
     *
     * @return array{current: float, percent: int, time_percent: int, status: string}
     */
    public function progress(ResultIndicator $indicator): array
    {
        $current = $this->current($indicator);
        $range = $indicator->target - $indicator->baseline;
        $percent = (int) max(0, min(999, round((($current - $indicator->baseline) / $range) * 100)));

        $totalDays = max(1, $indicator->starts_on->diffInDays($indicator->ends_on));
        $timePercent = (int) max(0, min(100, round($indicator->starts_on->diffInDays(today(), false) / $totalDays * 100)));

        $status = match (true) {
            $percent >= 100 => 'achieved',
            today()->gt($indicator->ends_on) => 'missed',
            $timePercent - $percent > 20 => 'behind',
            default => 'on_track',
        };

        return ['current' => $current, 'percent' => $percent, 'time_percent' => $timePercent, 'status' => $status];
    }

    /**
     * Rows for the CSV and PDF report.
     *
     * @return list<list<string|int|float|null>>
     */
    public function reportRows(string $tenantId): array
    {
        $rows = [['Indicator report', 'As at '.today()->toDateString()], ['Indicator', 'Programme', 'Unit', 'Baseline', 'Target', 'Current', 'Progress %', 'Period', 'Status']];
        $labels = ['achieved' => 'Achieved', 'missed' => 'Missed', 'behind' => 'Behind', 'on_track' => 'On track'];

        foreach (ResultIndicator::query()->where('tenant_id', $tenantId)->where('status', 'active')->with(['programme' => fn ($query) => $query->where('tenant_id', $tenantId)])->orderBy('programme_id')->orderBy('name')->get() as $indicator) {
            $progress = $this->progress($indicator);
            $rows[] = [$indicator->name, $indicator->programmeName(), $indicator->unit, $indicator->baseline, $indicator->target, $progress['current'], $progress['percent'], $indicator->starts_on->toDateString().' to '.$indicator->ends_on->toDateString(), $labels[$progress['status']]];
        }

        return $rows;
    }

    private function save(ResultIndicator $indicator, array $details): ResultIndicator
    {
        $candidate = clone $indicator;
        $candidate->fill(Arr::only($details, self::FIELDS));
        $candidate->baseline = $candidate->baseline ?? 0.0;
        $candidate->unit = $candidate->unit ?: 'people';
        if (! $candidate->starts_on || ! $candidate->ends_on || CarbonImmutable::parse($candidate->ends_on)->lt($candidate->starts_on)) {
            throw new DomainException('The indicator must end after it starts.');
        }
        if (! array_key_exists((string) $candidate->source, ResultIndicator::SOURCES)) {
            throw new DomainException('Choose where the figure comes from.');
        }
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

        $indicator->fill(Arr::only($candidate->getAttributes(), self::FIELDS));
        $indicator->save();
        $indicator->unsetRelation('programme')->unsetRelation('node');

        return $indicator;
    }
}
