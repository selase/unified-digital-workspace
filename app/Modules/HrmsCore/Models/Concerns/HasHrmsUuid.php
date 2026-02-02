<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Trait for generating and managing UUIDs on HRMS models.
 *
 * This trait provides UUID generation similar to the original HRMS uid field,
 * ensuring backward compatibility while using proper UUIDs.
 */
trait HasHrmsUuid
{
    /**
     * Boot the trait.
     */
    public static function bootHasHrmsUuid(): void
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
    public function initializeHasHrmsUuid(): void
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
