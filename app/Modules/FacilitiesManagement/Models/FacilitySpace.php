<?php

declare(strict_types=1);

namespace App\Modules\FacilitiesManagement\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $facility_building_id
 * @property string $name
 * @property string $code
 * @property int $floor_number
 * @property string $space_type
 * @property string|null $occupant_user_id
 * @property bool $is_occupied
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read FacilityBuilding|null $building
 * @property-read User|null $occupant
 */
final class FacilitySpace extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'facility_spaces';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'facility_building_id',
        'name',
        'code',
        'floor_number',
        'space_type',
        'occupant_user_id',
        'is_occupied',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<FacilityBuilding, $this>
     */
    public function building(): BelongsTo
    {
        return $this->belongsTo(FacilityBuilding::class, 'facility_building_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function occupant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'occupant_user_id', 'uuid');
    }

    /**
     * @return HasMany<FacilityAsset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(FacilityAsset::class, 'facility_space_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'floor_number' => 'integer',
            'is_occupied' => 'boolean',
        ];
    }
}
