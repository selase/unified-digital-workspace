<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use Closure;
use InvalidArgumentException;

final class ResourceLimitService
{
    /**
     * Serialize capacity checks and creation on the landlord tenant row, even
     * when the resource itself lives in a dedicated tenant database.
     *
     * @template T
     *
     * @param  Closure(): T  $create
     * @return T
     */
    public function create(Tenant $tenant, string $featureKey, Closure $create): mixed
    {
        return $tenant->getConnection()->transaction(function () use ($tenant, $featureKey, $create) {
            $currentTenant = Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $feature = $currentTenant->features()->where('feature_key', $featureKey)
                ->where(fn ($query) => $query->whereNull('meta->source')->orWhere('meta->source', '!=', 'package'))
                ->first();
            $limit = $feature !== null ? (int) ($feature->meta['value'] ?? 0) : null;

            if ($feature === null) {
                $packageFeature = $currentTenant->package()->first()?->features()->where('slug', $featureKey)->first();
                $limit = $packageFeature !== null ? (int) $packageFeature->getRelation('pivot')->getAttribute('value') : null;
            }

            abort_if($feature !== null && ! $feature->enabled, 403, 'This resource is disabled for your organisation.');

            if ($limit !== null && $limit >= 0) {
                $count = match ($featureKey) {
                    'users-limit' => User::query()->where(fn ($query) => $query
                        ->where('tenant_id', $tenant->id)
                        ->orWhereHas('tenants', fn ($memberships) => $memberships->where('tenants.id', $tenant->id)))->count(),
                    'projects-limit' => Project::query()->where('tenant_id', $tenant->id)->count(),
                    default => throw new InvalidArgumentException('Unsupported resource limit.'),
                };
                abort_if($count >= $limit, 403,
                    'Your plan has reached its '.str_replace('-limit', '', $featureKey).' limit. Please upgrade your plan.');
            }

            return $create();
        });
    }
}
