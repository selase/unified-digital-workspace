<?php

declare(strict_types=1);

namespace App\Modules\Volunteers\Services;

use App\Modules\ResultsFramework\Contracts\ResultMeasureProvider;
use App\Modules\ResultsFramework\Support\HasNoMeasureBreakdowns;
use App\Modules\ResultsFramework\Support\MeasureDefinition;
use App\Modules\ResultsFramework\Support\MeasureQuery;
use App\Modules\Volunteers\Models\VolHour;
use App\Modules\Volunteers\Models\VolVolunteer;
use DomainException;

final class VolunteerResultMeasures implements ResultMeasureProvider
{
    use HasNoMeasureBreakdowns;

    public function measures(): array
    {
        return [
            new MeasureDefinition('volunteers.hours_approved', 'Approved volunteer hours', 'volunteers', 'hours'),
            new MeasureDefinition('volunteers.active_volunteers', 'Active volunteers (current roster)', 'volunteers', 'people'),
        ];
    }

    public function value(string $tenantId, string $measureKey, MeasureQuery $query): float
    {
        return match ($measureKey) {
            'volunteers.hours_approved' => round(VolHour::query()->where('tenant_id', $tenantId)
                ->where('status', 'approved')->whereBetween('worked_on', $query->dates())->sum('minutes') / 60, 2),
            'volunteers.active_volunteers' => (float) VolVolunteer::query()->where('tenant_id', $tenantId)
                ->where('status', 'active')->where('joined_on', '<=', $query->to->toDateString())->count(),
            default => throw new DomainException('Unknown volunteer measure.'),
        };
    }
}
