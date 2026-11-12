<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Support;

use App\Modules\ResultsFramework\Models\ResultIndicator;
use Carbon\CarbonInterface;

final class MeasureQuery
{
    /** @param array<string, int|string> $filters */
    public function __construct(
        public CarbonInterface $from,
        public CarbonInterface $to,
        public array $filters = [],
    ) {}

    public static function forIndicator(ResultIndicator $indicator): self
    {
        return new self($indicator->starts_on, $indicator->ends_on->min(today()), $indicator->measure_filters ?? []);
    }

    /** @return array{string, string} */
    public function dates(): array
    {
        return [$this->from->toDateString(), $this->to->toDateString()];
    }
}
