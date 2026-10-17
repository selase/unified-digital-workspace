<?php

declare(strict_types=1);

namespace App\Modules\RealEstate\Services;

use App\Modules\RealEstate\Models\LandParcel;
use DomainException;

final class SpatialOverlapDetectorService
{
    /**
     * Check if proposed bounding coordinates overlap with any existing parcel in the project.
     *
     * @param  array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}  $bounds
     */
    public function assertNoOverlap(int $projectId, array $bounds, ?int $ignoreParcelId = null): void
    {
        $overlapping = $this->findOverlappingParcel($projectId, $bounds, $ignoreParcelId);

        if ($overlapping instanceof LandParcel) {
            throw new DomainException(
                "Spatial boundary conflict detected: proposed coordinates overlap with existing plot {$overlapping->plot_number} (ID: {$overlapping->uuid}). Double-allocation strictly blocked."
            );
        }
    }

    /**
     * @param  array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}  $bounds
     */
    public function findOverlappingParcel(int $projectId, array $bounds, ?int $ignoreParcelId = null): ?LandParcel
    {
        $minLat = $bounds['min_lat'];
        $maxLat = $bounds['max_lat'];
        $minLng = $bounds['min_lng'];
        $maxLng = $bounds['max_lng'];

        /** @var LandParcel|null $candidate */
        $candidate = LandParcel::query()
            ->where('real_estate_project_id', $projectId)
            ->when($ignoreParcelId, fn ($q) => $q->where('id', '!=', $ignoreParcelId))
            ->whereNotNull('min_lat')
            ->whereNotNull('max_lat')
            ->whereNotNull('min_lng')
            ->whereNotNull('max_lng')
            ->where('min_lat', '<', $maxLat)
            ->where('max_lat', '>', $minLat)
            ->where('min_lng', '<', $maxLng)
            ->where('max_lng', '>', $minLng)
            ->first();

        return $candidate;
    }
}
