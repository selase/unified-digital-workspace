<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Models\Concerns;

use Illuminate\Support\Str;

trait HasIncidentUuid
{
    public static function bootHasIncidentUuid(): void
    {
        static::creating(function ($model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function initializeHasIncidentUuid(): void
    {
        $this->mergeFillable(['uuid']);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
