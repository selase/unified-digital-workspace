<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Models\Concerns;

use Illuminate\Support\Str;

trait HasProjectUuid
{
    protected static function bootHasProjectUuid(): void
    {
        static::creating(function ($model): void {
            if (! $model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
