<?php

declare(strict_types=1);

namespace App\Modules\Grants\Services;

use App\Modules\Grants\Models\GrtInstalment;
use App\Modules\ResultsFramework\Contracts\ResultMeasureProvider;
use App\Modules\ResultsFramework\Support\HasNoMeasureBreakdowns;
use App\Modules\ResultsFramework\Support\MeasureDefinition;
use App\Modules\ResultsFramework\Support\MeasureQuery;
use DomainException;

final class GrantResultMeasures implements ResultMeasureProvider
{
    use HasNoMeasureBreakdowns;

    public function measures(): array
    {
        return [new MeasureDefinition('grants.funds_received', 'Grant funds received', 'grants', 'currency')];
    }

    public function value(string $tenantId, string $measureKey, MeasureQuery $query): float
    {
        if ($measureKey !== 'grants.funds_received') {
            throw new DomainException('Unknown grant measure.');
        }

        return round(GrtInstalment::query()->where('tenant_id', $tenantId)->where('status', 'received')
            ->whereBetween('received_on', $query->dates())->sum('amount') / 100, 2);
    }
}
