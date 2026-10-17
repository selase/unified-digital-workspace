<?php

declare(strict_types=1);

namespace App\Modules\FacilitiesManagement\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $facility_asset_id
 * @property string $title
 * @property string|null $description
 * @property string $frequency
 * @property int $interval_days
 * @property \Carbon\CarbonInterface|\Illuminate\Support\Carbon|string|null $last_performed_at
 * @property \Carbon\CarbonInterface|\Illuminate\Support\Carbon|string|null $next_due_date
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read FacilityAsset|null $asset
 */
final class PreventiveMaintenanceSchedule extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'preventive_maintenance_schedules';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'facility_asset_id',
        'title',
        'description',
        'frequency',
        'interval_days',
        'last_performed_at',
        'next_due_date',
        'is_active',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<FacilityAsset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(FacilityAsset::class, 'facility_asset_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<FacilityWorkOrder, $this>
     */
    public function workOrders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FacilityWorkOrder::class, 'preventive_maintenance_schedule_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interval_days' => 'integer',
            'last_performed_at' => 'datetime',
            'next_due_date' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
