<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Support;

final readonly class LogframeRow
{
    /**
     * @param  list<array{name: string, baseline: float, target: float, current: float|string, status: string, means_of_verification: ?string, breakdowns: array<string, array<string, float>|null>}>  $indicators
     */
    public function __construct(
        public string $level,
        public int $depth,
        public string $title,
        public array $indicators,
        public ?string $assumptions,
        public ?string $risks,
    ) {}
}
