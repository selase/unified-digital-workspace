<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Support;

use Carbon\CarbonInterface;

final class MeasureQuery
{
    /** @param array<string, int|string> $filters */
    public function __construct(
        public CarbonInterface $from,
        public CarbonInterface $to,
        public array $filters = [],
    ) {}

    /** @return array{string, string} */
    public function dates(): array
    {
        return [$this->from->toDateString(), $this->to->toDateString()];
    }
}
