<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Organization;

use App\Modules\HrmsCore\Database\Factories\GradeFactory;
use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Grade model - Organization hierarchy levels.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $can_recommend_leave
 * @property bool $can_approve_leave
 * @property bool $can_appraise
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @use HasFactory<GradeFactory>
 */
final class Grade extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<GradeFactory> */
    use HasFactory;

    use HasHrmsUuid;

    /**
     * The table associated with the model.
     */
    protected $table = 'hrms_grades';

    /**
     * The connection name for the model.
     */
    protected $connection = 'landlord';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'can_recommend_leave',
        'can_approve_leave',
        'can_appraise',
        'sort_order',
    ];

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<static>
     */
    protected static function newFactory(): \Illuminate\Database\Eloquent\Factories\Factory
    {
        return GradeFactory::new();
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (Grade $grade): void {
            if (empty($grade->slug)) {
                $grade->slug = Str::slug($grade->name);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'can_recommend_leave' => 'boolean',
            'can_approve_leave' => 'boolean',
            'can_appraise' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // Note: Relationships to Employee, SalaryLevel, SalaryStep, MarketPremium,
    // and OtherAllowanceType will be added in Phase 2 when those models are created.
}
