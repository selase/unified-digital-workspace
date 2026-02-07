<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Models\Concerns;

use Illuminate\Support\Str;

trait HasDocumentUuid
{
    protected static function bootHasDocumentUuid(): void
    {
        static::creating(function ($model): void {
            if (! $model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
