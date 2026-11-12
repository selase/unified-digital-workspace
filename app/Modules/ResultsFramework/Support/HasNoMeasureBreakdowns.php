<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Support;

use DomainException;

trait HasNoMeasureBreakdowns
{
    public function breakdown(string $tenantId, string $measureKey, string $dimension, MeasureQuery $query): array
    {
        throw new DomainException('This measure does not offer that breakdown.');
    }
}
