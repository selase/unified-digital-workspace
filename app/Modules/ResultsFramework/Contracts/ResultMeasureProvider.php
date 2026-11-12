<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Contracts;

use App\Modules\ResultsFramework\Support\MeasureDefinition;
use App\Modules\ResultsFramework\Support\MeasureQuery;

interface ResultMeasureProvider
{
    /** @return list<MeasureDefinition> */
    public function measures(): array;

    public function value(string $tenantId, string $measureKey, MeasureQuery $query): float;

    /** @return array<string, float> */
    public function breakdown(string $tenantId, string $measureKey, string $dimension, MeasureQuery $query): array;
}
