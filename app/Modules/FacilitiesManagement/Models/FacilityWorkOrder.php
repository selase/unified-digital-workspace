<?php

declare(strict_types=1);

namespace App\Modules\FacilitiesManagement\Models;

use App\Models\User;
use App\Modules\VendorMarketplace\Models\ServiceJob;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $work_order_number
 * @property string $mode
 * @property int|null $facility_building_id
 * @property int|null $facility_space_id
 * @property int|null $facility_asset_id
 * @property int|null $preventive_maintenance_schedule_id
 * @property string $requester_id
 * @property string|null $assigned_technician_id
 * @property string $category
 * @property string $title
 * @property string|null $description
 * @property string $priority
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property \Illuminate\Support\Carbon|null $scheduled_due_date
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property string|null $completion_notes
 * @property int|null $service_job_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class FacilityWorkOrder extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const MODE_INSTITUTIONAL = 'mode_a_institutional';

    public const MODE_OCCUPANT_MARKETPLACE = 'mode_b_occupant_marketplace';

    public const STATUS_OPEN = 'open';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'facility_work_orders';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'work_order_number',
        'mode',
        'facility_building_id',
        'facility_space_id',
        'facility_asset_id',
        'requester_id',
        'assigned_technician_id',
        'category',
        'title',
        'description',
        'priority',
        'status',
        'due_date',
        'scheduled_due_date',
        'completed_at',
        'completion_notes',
        'service_job_id',
        'preventive_maintenance_schedule_id',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<PreventiveMaintenanceSchedule, $this>
     */
    public function preventiveMaintenanceSchedule(): BelongsTo
    {
        return $this->belongsTo(PreventiveMaintenanceSchedule::class, 'preventive_maintenance_schedule_id');
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
     * @return BelongsTo<FacilityAsset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(FacilityAsset::class, 'facility_asset_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id', 'uuid');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_technician_id', 'uuid');
    }

    /**
     * @return BelongsTo<ServiceJob, $this>
     */
    public function serviceJob(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class, 'service_job_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'scheduled_due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }
}
