<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Services;

use App\Modules\ResultsFramework\Models\ResultIndicator;
use App\Modules\ResultsFramework\Models\ResultTarget;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Collection;

final class TargetService
{
    public function set(ResultIndicator $indicator, CarbonInterface $start, CarbonInterface $end, float $value): ResultTarget
    {
        if (! is_finite($value) || abs($value) > 999999999999.99 || round($value, 2) !== $value) {
            throw new DomainException('Enter a target with up to two decimal places.');
        }

        $from = $start->toDateString();
        $to = $end->toDateString();

        return $indicator->getConnection()->transaction(function () use ($indicator, $from, $to, $value): ResultTarget {
            $locked = $this->lock($indicator);
            if ($from > $to || $from < $locked->starts_on->toDateString() || $to > $locked->ends_on->toDateString()) {
                throw new DomainException('The target period must be inside the indicator dates.');
            }

            $existing = $locked->targets()->where('tenant_id', $locked->tenant_id);
            if ((clone $existing)->where('period_start', '<=', $to)->where('period_end', '>=', $from)
                ->where(fn ($query) => $query->where('period_start', '<>', $from)->orWhere('period_end', '<>', $to))->exists()) {
                throw new DomainException('Target periods cannot overlap.');
            }

            return $existing->updateOrCreate([
                'tenant_id' => $locked->tenant_id,
                'period_start' => $from,
                'period_end' => $to,
            ], ['target_value' => $value]);
        });
    }

    /** @return Collection<int, ResultTarget> */
    public function forIndicator(ResultIndicator $indicator): Collection
    {
        return $indicator->targets()->where('tenant_id', $indicator->tenant_id)->orderBy('period_start')->get();
    }

    /** @return Collection<int, ResultTarget> */
    public function generate(ResultIndicator $indicator, string $frequency): Collection
    {
        if (! in_array($frequency, ['monthly', 'quarterly'], true)) {
            throw new DomainException('Choose monthly or quarterly targets.');
        }

        return $indicator->getConnection()->transaction(function () use ($indicator, $frequency): Collection {
            $locked = $this->lock($indicator);
            if ($locked->targets()->where('tenant_id', $locked->tenant_id)->exists()) {
                throw new DomainException('Period targets already exist. Edit the existing figures individually.');
            }

            $periods = [];
            $start = CarbonImmutable::parse($locked->starts_on->toDateString());
            $end = CarbonImmutable::parse($locked->ends_on->toDateString());
            while ($start->lte($end)) {
                $boundary = $frequency === 'monthly' ? $start->endOfMonth() : $start->endOfQuarter();
                $lastDay = $boundary->startOfDay()->min($end);
                $periods[] = ['period_start' => $start->toDateString(), 'period_end' => $lastDay->toDateString()];
                $start = $lastDay->addDay();
            }

            $cents = (int) round($locked->target * 100);
            $perPeriod = intdiv($cents, count($periods));
            $remainder = $cents % count($periods);
            foreach ($periods as $index => $period) {
                $adjustment = $index < abs($remainder) ? ($remainder <=> 0) : 0;
                $locked->targets()->create($period + [
                    'tenant_id' => $locked->tenant_id,
                    'target_value' => ($perPeriod + $adjustment) / 100,
                ]);
            }

            return $this->forIndicator($locked);
        });
    }

    private function lock(ResultIndicator $indicator): ResultIndicator
    {
        return ResultIndicator::query()->where('tenant_id', $indicator->tenant_id)->whereKey($indicator->id)
            ->lockForUpdate()->firstOrFail();
    }
}
