<?php

declare(strict_types=1);

namespace App\Modules\RealEstate\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string|null $tenant_id
 * @property int $real_estate_project_id
 * @property string $plot_number
 * @property string|null $block_number
 * @property string $size_dimension
 * @property string|float $acreage
 * @property int $price_pesewas
 * @property string $tenure_type
 * @property string $current_stage
 * @property bool $is_legacy
 * @property string $status
 * @property string|float|null $min_lat
 * @property string|float|null $max_lat
 * @property string|float|null $min_lng
 * @property string|float|null $max_lng
 * @property array<string, mixed>|null $boundary_coordinates_json
 * @property \Illuminate\Support\Carbon|null $inspection_date
 * @property string|null $lands_commission_search_number
 * @property \Illuminate\Support\Carbon|null $search_issue_date
 * @property bool $has_traditional_consent_letter
 * @property bool $has_state_allocation_note
 * @property bool $is_due_diligence_verified
 * @property string|null $due_diligence_verified_by_id
 * @property \Illuminate\Support\Carbon|null $due_diligence_verified_at
 * @property string|null $indenture_reference
 * @property string|null $land_title_certificate_number
 * @property bool $stamp_duty_paid
 * @property bool $pillars_and_wall_secured
 * @property \Illuminate\Support\Carbon|null $registration_date
 * @property string|null $registration_verified_by_id
 * @property string|null $architect_gia_number
 * @property string|null $structural_engineer_ghie_number
 * @property string|null $mmda_permit_number
 * @property \Illuminate\Support\Carbon|null $permit_issue_date
 * @property bool $requires_epa_clearance
 * @property string|null $epa_clearance_number
 * @property bool $is_permit_active
 * @property string|null $permit_verified_by_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read RealEstateProject|null $project
 * @property-read ClientPlotAllocation|null $allocation
 */
final class LandParcel extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    public const STAGE_DUE_DILIGENCE = 'IN_DUE_DILIGENCE';

    public const STAGE_REGISTRATION = 'PURCHASE_AND_REGISTRATION';

    public const STAGE_PERMITTING = 'PERMITTING_AND_PRE_CONSTRUCTION';

    public const STAGE_READY_TO_BUILD = 'PROJECT_READY_FOR_BUILD';

    protected $table = 'land_parcels';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'real_estate_project_id',
        'plot_number',
        'block_number',
        'size_dimension',
        'acreage',
        'price_pesewas',
        'tenure_type',
        'current_stage',
        'is_legacy',
        'status',
        'min_lat',
        'max_lat',
        'min_lng',
        'max_lng',
        'boundary_coordinates_json',
        'inspection_date',
        'lands_commission_search_number',
        'search_issue_date',
        'has_traditional_consent_letter',
        'has_state_allocation_note',
        'is_due_diligence_verified',
        'due_diligence_verified_by_id',
        'due_diligence_verified_at',
        'indenture_reference',
        'land_title_certificate_number',
        'stamp_duty_paid',
        'pillars_and_wall_secured',
        'registration_date',
        'registration_verified_by_id',
        'architect_gia_number',
        'structural_engineer_ghie_number',
        'mmda_permit_number',
        'permit_issue_date',
        'requires_epa_clearance',
        'epa_clearance_number',
        'is_permit_active',
        'permit_verified_by_id',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<RealEstateProject, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(RealEstateProject::class, 'real_estate_project_id');
    }

    /**
     * @return HasMany<LandDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(LandDocument::class, 'land_parcel_id');
    }

    /**
     * @return HasOne<ClientPlotAllocation, $this>
     */
    public function allocation(): HasOne
    {
        return $this->hasOne(ClientPlotAllocation::class, 'land_parcel_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dueDiligenceVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'due_diligence_verified_by_id', 'uuid');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function registrationVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registration_verified_by_id', 'uuid');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function permitVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'permit_verified_by_id', 'uuid');
    }

    public function formattedPrice(): string
    {
        return 'GHS '.number_format($this->price_pesewas / 100, 2);
    }

    public function hasUnsecuredBoundaryRisk(): bool
    {
        return in_array($this->current_stage, [
            self::STAGE_REGISTRATION,
            self::STAGE_PERMITTING,
            self::STAGE_READY_TO_BUILD,
        ], true) && ! $this->pillars_and_wall_secured;
    }

    public function stageStepNumber(): int
    {
        return match ($this->current_stage) {
            self::STAGE_DUE_DILIGENCE => 1,
            self::STAGE_REGISTRATION => 2,
            self::STAGE_PERMITTING => 3,
            self::STAGE_READY_TO_BUILD => 4,
            default => 1,
        };
    }

    public function hasSpatialCoordinates(): bool
    {
        return $this->min_lat !== null && $this->max_lat !== null && $this->min_lng !== null && $this->max_lng !== null;
    }

    public function checkSpatialOverlap(): ?self
    {
        if (! $this->hasSpatialCoordinates() || ! $this->real_estate_project_id) {
            return null;
        }

        return app(\App\Modules\RealEstate\Services\SpatialOverlapDetectorService::class)->findOverlappingParcel(
            $this->real_estate_project_id,
            [
                'min_lat' => (float) $this->min_lat,
                'max_lat' => (float) $this->max_lat,
                'min_lng' => (float) $this->min_lng,
                'max_lng' => (float) $this->max_lng,
            ],
            $this->id
        );
    }

    public function isSearchExpired(): bool
    {
        if ($this->is_legacy || ! $this->search_issue_date) {
            return false;
        }

        return Carbon::parse($this->search_issue_date)->diffInDays(now()) > 30;
    }

    public function searchDaysRemaining(): int
    {
        if (! $this->search_issue_date) {
            return 0;
        }

        return max(0, 30 - (int) Carbon::parse($this->search_issue_date)->diffInDays(now()));
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'acreage' => 'decimal:4',
            'price_pesewas' => 'integer',
            'is_legacy' => 'boolean',
            'min_lat' => 'decimal:7',
            'max_lat' => 'decimal:7',
            'min_lng' => 'decimal:7',
            'max_lng' => 'decimal:7',
            'boundary_coordinates_json' => 'array',
            'inspection_date' => 'date',
            'search_issue_date' => 'date',
            'has_traditional_consent_letter' => 'boolean',
            'has_state_allocation_note' => 'boolean',
            'is_due_diligence_verified' => 'boolean',
            'due_diligence_verified_at' => 'datetime',
            'stamp_duty_paid' => 'boolean',
            'pillars_and_wall_secured' => 'boolean',
            'registration_date' => 'date',
            'permit_issue_date' => 'date',
            'requires_epa_clearance' => 'boolean',
            'is_permit_active' => 'boolean',
        ];
    }
}
