<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Models\Concerns;

use Illuminate\Support\Str;

trait HasIncidentPrimaryUuid
{
    public static function bootHasIncidentPrimaryUuid(): void
    {
        static::creating(function ($model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function initializeHasIncidentPrimaryUuid(): void
    {
        $this->mergeFillable(['id']);
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
