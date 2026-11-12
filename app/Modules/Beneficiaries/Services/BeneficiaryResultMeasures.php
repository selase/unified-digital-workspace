<?php

declare(strict_types=1);

namespace App\Modules\Beneficiaries\Services;

use App\Modules\Beneficiaries\Models\BenBeneficiary;
use App\Modules\Beneficiaries\Models\BenService;
use App\Modules\ResultsFramework\Contracts\ProvidesMeasureFilterOptions;
use App\Modules\ResultsFramework\Contracts\ResultMeasureProvider;
use App\Modules\ResultsFramework\Support\HasNoMeasureBreakdowns;
use App\Modules\ResultsFramework\Support\MeasureDefinition;
use App\Modules\ResultsFramework\Support\MeasureQuery;
use DomainException;

final class BeneficiaryResultMeasures implements ProvidesMeasureFilterOptions, ResultMeasureProvider
{
    use HasNoMeasureBreakdowns;

    public function measures(): array
    {
        $filters = ['service_type' => BenBeneficiary::SERVICE_TYPES];

        return [
            new MeasureDefinition('beneficiaries.people_reached', 'People reached', 'beneficiaries', 'people', $filters + ['programme' => []]),
            new MeasureDefinition('beneficiaries.services_delivered', 'Services delivered', 'beneficiaries', 'services', $filters),
            new MeasureDefinition('beneficiaries.new_registrations', 'New beneficiary registrations', 'beneficiaries', 'people'),
        ];
    }

    public function filterOptions(string $tenantId, string $measureKey): array
    {
        if ($measureKey !== 'beneficiaries.people_reached') {
            return [];
        }

        return ['programme' => BenBeneficiary::query()->where('tenant_id', $tenantId)
            ->whereNotNull('programme')->where('programme', '<>', '')
            ->distinct()->orderBy('programme')->pluck('programme', 'programme')->all()];
    }

    public function value(string $tenantId, string $measureKey, MeasureQuery $query): float
    {
        if ($measureKey === 'beneficiaries.new_registrations') {
            return (float) BenBeneficiary::query()->where('tenant_id', $tenantId)
                ->whereBetween('enrolled_on', $query->dates())->count();
        }

        $services = BenService::query()->where('tenant_id', $tenantId)->whereBetween('delivered_on', $query->dates());
        if (isset($query->filters['service_type'])) {
            $services->where('type', $query->filters['service_type']);
        }
        if (isset($query->filters['programme'])) {
            $services->whereHas('beneficiary', fn ($beneficiary) => $beneficiary
                ->where('tenant_id', $tenantId)->where('programme', $query->filters['programme']));
        }

        return match ($measureKey) {
            'beneficiaries.people_reached' => (float) $services->distinct()->count('beneficiary_id'),
            'beneficiaries.services_delivered' => (float) $services->count(),
            default => throw new DomainException('Unknown beneficiary measure.'),
        };
    }
}
