<?php

declare(strict_types=1);

namespace App\Modules\Beneficiaries\Services;

use App\Modules\Beneficiaries\Models\BenBeneficiary;
use App\Modules\Beneficiaries\Models\BenService;
use App\Modules\ResultsFramework\Contracts\ProvidesMeasureFilterOptions;
use App\Modules\ResultsFramework\Contracts\ResultMeasureProvider;
use App\Modules\ResultsFramework\Support\Dimensions;
use App\Modules\ResultsFramework\Support\MeasureDefinition;
use App\Modules\ResultsFramework\Support\MeasureQuery;
use DomainException;
use Illuminate\Database\Eloquent\Builder;

final class BeneficiaryResultMeasures implements ProvidesMeasureFilterOptions, ResultMeasureProvider
{
    public function measures(): array
    {
        $filters = ['service_type' => BenBeneficiary::SERVICE_TYPES];

        $dimensions = array_keys(Dimensions::LABELS);

        return [
            new MeasureDefinition('beneficiaries.people_reached', 'People reached', 'beneficiaries', 'people', $filters + ['programme' => []], $dimensions),
            new MeasureDefinition('beneficiaries.services_delivered', 'Services delivered', 'beneficiaries', 'services', $filters, $dimensions),
            new MeasureDefinition('beneficiaries.new_registrations', 'New beneficiary registrations', 'beneficiaries', 'people', dimensions: $dimensions),
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

        $services = $this->services($tenantId, $query);

        return match ($measureKey) {
            'beneficiaries.people_reached' => (float) $services->distinct()->count('beneficiary_id'),
            'beneficiaries.services_delivered' => (float) $services->count(),
            default => throw new DomainException('Unknown beneficiary measure.'),
        };
    }

    /** @return array<string, float> */
    public function breakdown(string $tenantId, string $measureKey, string $dimension, MeasureQuery $query): array
    {
        $people = BenBeneficiary::query()->where('tenant_id', $tenantId)->select(['id', 'gender', 'date_of_birth', 'region']);
        if ($measureKey === 'beneficiaries.new_registrations') {
            $people->whereBetween('enrolled_on', $query->dates());
        } else {
            $services = $this->services($tenantId, $query);
            $people->whereIn('id', (clone $services)->select('beneficiary_id'));
            if ($measureKey === 'beneficiaries.services_delivered') {
                $people->addSelect(['service_count' => $services->selectRaw('count(*)')
                    ->whereColumn('ben_services.beneficiary_id', 'ben_beneficiaries.id')]);
            }
        }

        $buckets = [];
        foreach ($people->lazyById() as $person) {
            $bucket = match ($dimension) {
                'gender' => isset(Dimensions::FIXED_BUCKETS['gender'][$person->gender]) ? $person->gender : 'unknown',
                'age_band' => Dimensions::ageBand($person->date_of_birth, $query->to),
                'location' => mb_trim((string) $person->region) ?: 'unknown',
                default => throw new DomainException('This measure does not offer that breakdown.'),
            };

            $buckets[$bucket] = ($buckets[$bucket] ?? 0.0) + (float) ($person->service_count ?? 1);
        }

        return $buckets;
    }

    /** @return Builder<BenService> */
    private function services(string $tenantId, MeasureQuery $query): Builder
    {
        $services = BenService::query()->where('tenant_id', $tenantId)->whereBetween('delivered_on', $query->dates());
        if (isset($query->filters['service_type'])) {
            $services->where('type', $query->filters['service_type']);
        }
        if (isset($query->filters['programme'])) {
            $services->whereHas('beneficiary', fn ($beneficiary) => $beneficiary
                ->where('tenant_id', $tenantId)->where('programme', $query->filters['programme']));
        }

        return $services;
    }
}
