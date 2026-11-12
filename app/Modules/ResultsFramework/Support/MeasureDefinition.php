<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Support;

final class MeasureDefinition
{
    /**
     * @param  array<string, array<int|string, string>>  $filters
     * @param  list<string>  $dimensions
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $module,
        public string $unit,
        public array $filters = [],
        public array $dimensions = [],
    ) {}
}
