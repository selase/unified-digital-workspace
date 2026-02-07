<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Models\Concerns;

use Illuminate\Support\Str;

trait HasCmsUuid
{
    /**
     * Boot the trait.
     */
    public static function bootHasCmsUuid(): void
    {
        static::creating(function ($model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Initialize the trait.
     */
    public function initializeHasCmsUuid(): void
    {
        $this->mergeFillable(['uuid']);
    }

    /**
     * Get the route key name for model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Scope a query to find by UUID.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeByUuid($query, string $uuid)
    {
        return $query->where('uuid', $uuid);
    }
}
