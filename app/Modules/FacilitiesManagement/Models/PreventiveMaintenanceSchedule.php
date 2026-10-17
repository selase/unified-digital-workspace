<?php

declare(strict_types=1);

namespace App\Modules\FacilitiesManagement\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
