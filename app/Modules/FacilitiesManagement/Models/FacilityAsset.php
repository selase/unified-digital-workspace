<?php

declare(strict_types=1);

namespace App\Modules\FacilitiesManagement\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $facility_building_id
 * @property int|null $facility_space_id
 * @property string $asset_tag
 * @property string $name
 * @property string $category
 * @property string|null $serial_number
 * @property string|null $manufacturer
 * @property string|null $model_number
 * @property \Illuminate\Support\Carbon|null $purchase_date
 * @property int $purchase_cost_pesewas
 * @property \Illuminate\Support\Carbon|null $warranty_expiry_date
 * @property string $status
 * @property string $criticality
 * @property \Illuminate\Support\Carbon|null $last_serviced_at
 * @property \Illuminate\Support\Carbon|null $next_service_due
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read FacilityBuilding|null $building
 */
final class FacilityAsset extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'facility_assets';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'facility_building_id',
        'facility_space_id',
        'asset_tag',
        'name',
        'category',
        'serial_number',
        'manufacturer',
        'model_number',
        'purchase_date',
        'purchase_cost_pesewas',
        'warranty_expiry_date',
        'status',
        'criticality',
        'last_serviced_at',
        'next_service_due',
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
     * @return BelongsTo<FacilitySpace, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(FacilitySpace::class, 'facility_space_id');
    }

    /**
     * @return HasMany<PreventiveMaintenanceSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(PreventiveMaintenanceSchedule::class, 'facility_asset_id');
    }

    /**
     * @return HasMany<FacilityWorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(FacilityWorkOrder::class, 'facility_asset_id');
    }

    public function formattedCost(): string
    {
        return 'GHS '.number_format($this->purchase_cost_pesewas / 100, 2);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_cost_pesewas' => 'integer',
            'warranty_expiry_date' => 'date',
            'last_serviced_at' => 'datetime',
            'next_service_due' => 'date',
        ];
    }
}
