<?php

declare(strict_types=1);

namespace App\Modules\FacilitiesManagement\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class FacilityBuilding extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'facility_buildings';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'code',
        'address',
        'floors_count',
        'total_area_sqm',
        'manager_id',
        'status',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id', 'uuid');
    }

    /**
     * @return HasMany<FacilitySpace, $this>
     */
    public function spaces(): HasMany
    {
        return $this->hasMany(FacilitySpace::class, 'facility_building_id');
    }

    /**
     * @return HasMany<FacilityAsset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(FacilityAsset::class, 'facility_building_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'floors_count' => 'integer',
            'total_area_sqm' => 'decimal:2',
        ];
    }
}
