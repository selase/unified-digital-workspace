<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Contracts;

interface ProvidesMeasureFilterOptions
{
    /** @return array<string, array<int|string, string>> */
    public function filterOptions(string $tenantId, string $measureKey): array;
}
