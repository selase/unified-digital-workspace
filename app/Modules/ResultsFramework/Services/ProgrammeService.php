<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Grants\Models\GrtGrant;
use App\Modules\ResultsFramework\Models\ResultIndicator;
use App\Modules\ResultsFramework\Models\ResultProgramme;
use App\Services\ModuleManager;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

final class ProgrammeService
{
    private const FIELDS = ['description', 'status', 'starts_on', 'ends_on', 'owner_id', 'grant_id'];

    public function create(string $tenantId, string $name, array $details = []): ResultProgramme
    {
        $programme = new ResultProgramme(['tenant_id' => $tenantId, 'name' => mb_trim($name)]);
        $programme->fill(Arr::only($details, self::FIELDS));
        $this->validate($programme);
        $programme->save();

        return $programme;
    }

    public function update(ResultProgramme $programme, array $details): ResultProgramme
    {
        $programme->fill(Arr::only($details, ['name', ...self::FIELDS]));
        $programme->name = mb_trim($programme->name);
        $this->validate($programme);
        $programme->save();

        return $programme;
    }

    public function close(ResultProgramme $programme): void
    {
        $this->update($programme, ['status' => 'closed']);
    }

    /** @return Collection<int, GrtGrant>|null */
    public function availableGrants(string $tenantId): ?Collection
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        if (! app(ModuleManager::class)->isEnabledForTenant('grants', $tenant)) {
            return null;
        }

        return GrtGrant::query()->where('tenant_id', $tenantId)->orderBy('title')->get(['id', 'title']);
    }

    /** @return list<array{programme: ResultProgramme, indicators: list<array{name: string, target: float, current: float|string, status: string}>}> */
    public function resultsForGrant(GrtGrant $grant, User $viewer): array
    {
        if (! $viewer->can('results.view')) {
            return [];
        }

        $tenant = $grant->tenant;
        $modules = app(ModuleManager::class);
        if (! $tenant instanceof Tenant || ! $modules->isEnabledForTenant('grants', $tenant) || ! $modules->isEnabledForTenant('results-framework', $tenant)) {
            return [];
        }

        $indicators = app(IndicatorService::class);

        return ResultProgramme::query()->where('tenant_id', $tenant->id)->where('grant_id', $grant->id)
            ->with(['indicators' => fn ($query) => $query->where('tenant_id', $tenant->id)->where('status', 'active')->orderBy('name')])
            ->orderBy('name')->get()->map(fn (ResultProgramme $programme): array => [
                'programme' => $programme,
                'indicators' => $programme->indicators->map(function (ResultIndicator $indicator) use ($indicators, $viewer, $tenant): array {
                    $indicator->setRelation('tenant', $tenant);
                    $progress = $indicators->progress($indicator, $viewer, includePeriods: false);

                    return [
                        'name' => $indicator->name,
                        'target' => $indicator->target,
                        'current' => $progress['current'] ?? 'Unavailable',
                        'status' => ucfirst(str_replace('_', ' ', $progress['status'])),
                    ];
                })->all(),
            ])->all();
    }

    private function validate(ResultProgramme $programme): void
    {
        if ($programme->name === '' || mb_strlen($programme->name) > 255) {
            throw new DomainException('Enter a programme name of up to 255 characters.');
        }

        if (! in_array($programme->status, ResultProgramme::STATUSES, true)) {
            throw new DomainException('Choose a valid programme status.');
        }

        if ($programme->starts_on && $programme->ends_on && CarbonImmutable::parse($programme->ends_on)->lt($programme->starts_on)) {
            throw new DomainException('The programme must end on or after it starts.');
        }

        if ($programme->grant_id !== null && (! $programme->exists || $programme->isDirty('grant_id'))) {
            $tenant = Tenant::query()->findOrFail($programme->tenant_id);
            if (! app(ModuleManager::class)->isEnabledForTenant('grants', $tenant)
                || ! GrtGrant::query()->where('tenant_id', $tenant->id)->whereKey($programme->grant_id)->exists()) {
                throw new DomainException('Choose an available grant in this organisation.');
            }
        }

        if (ResultProgramme::query()->where('tenant_id', $programme->tenant_id)->where('name', $programme->name)
            ->when($programme->exists, fn ($query) => $query->whereKeyNot($programme->getKey()))->exists()) {
            throw new DomainException('A programme with this name already exists.');
        }
    }
}
