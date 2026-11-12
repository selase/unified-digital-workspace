<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Services;

use App\Modules\ResultsFramework\Models\ResultProgramme;
use Carbon\CarbonImmutable;
use DomainException;
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

        if (ResultProgramme::query()->where('tenant_id', $programme->tenant_id)->where('name', $programme->name)
            ->when($programme->exists, fn ($query) => $query->whereKeyNot($programme->getKey()))->exists()) {
            throw new DomainException('A programme with this name already exists.');
        }
    }
}
