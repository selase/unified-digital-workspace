<?php

declare(strict_types=1);

namespace App\Modules\Donations\Services;

use App\Modules\Donations\Models\DonCampaign;
use App\Modules\Donations\Models\DonDonation;
use App\Modules\ResultsFramework\Contracts\ProvidesMeasureFilterOptions;
use App\Modules\ResultsFramework\Contracts\ResultMeasureProvider;
use App\Modules\ResultsFramework\Support\HasNoMeasureBreakdowns;
use App\Modules\ResultsFramework\Support\MeasureDefinition;
use App\Modules\ResultsFramework\Support\MeasureQuery;
use DomainException;

final class DonationResultMeasures implements ProvidesMeasureFilterOptions, ResultMeasureProvider
{
    use HasNoMeasureBreakdowns;

    public function measures(): array
    {
        return [
            new MeasureDefinition('donations.amount_raised', 'Donations raised', 'donations', 'currency', ['campaign_id' => []]),
            new MeasureDefinition('donations.gifts_received', 'Gifts received', 'donations', 'gifts'),
            new MeasureDefinition('donations.donors_who_gave', 'Donors who gave', 'donations', 'donors'),
        ];
    }

    public function filterOptions(string $tenantId, string $measureKey): array
    {
        return $measureKey === 'donations.amount_raised'
            ? ['campaign_id' => DonCampaign::query()->where('tenant_id', $tenantId)->orderBy('name')->pluck('name', 'id')->all()]
            : [];
    }

    public function value(string $tenantId, string $measureKey, MeasureQuery $query): float
    {
        $gifts = DonDonation::query()->where('tenant_id', $tenantId)->whereNull('voided_at')->whereBetween('date', $query->dates());
        if (isset($query->filters['campaign_id'])) {
            $gifts->where('campaign_id', $query->filters['campaign_id']);
        }

        return match ($measureKey) {
            'donations.amount_raised' => round($gifts->sum('amount') / 100, 2),
            'donations.gifts_received' => (float) $gifts->count(),
            'donations.donors_who_gave' => (float) $gifts->distinct()->count('donor_id'),
            default => throw new DomainException('Unknown donation measure.'),
        };
    }
}
