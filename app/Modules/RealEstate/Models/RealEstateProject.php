<?php

declare(strict_types=1);

namespace App\Modules\RealEstate\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class RealEstateProject extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'real_estate_projects';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'code',
        'location',
        'region',
        'description',
        'total_acreage',
        'status',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<LandParcel, $this>
     */
    public function parcels(): HasMany
    {
        return $this->hasMany(LandParcel::class, 'real_estate_project_id');
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
