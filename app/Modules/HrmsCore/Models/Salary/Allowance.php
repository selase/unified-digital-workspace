<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Salary;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Organization\Grade;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Allowance model - Specific allowance definitions with amounts.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $name
 * @property string $slug
 * @property int $allowance_type_id
 * @property int|null $grade_id
 * @property string $amount
 * @property string $frequency
 * @property string|null $description
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class Allowance extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_allowances';

    protected $connection = 'landlord';

    public const FREQUENCY_MONTHLY = 'monthly';

    public const FREQUENCY_ANNUAL = 'annual';

    public const FREQUENCY_ONE_TIME = 'one-time';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'allowance_type_id',
        'grade_id',
        'amount',
        'frequency',
        'description',
        'is_active',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'amount' => 0,
        'frequency' => 'monthly',
        'is_active' => true,
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (Allowance $allowance): void {
            if (empty($allowance->slug)) {
                $allowance->slug = Str::slug($allowance->name);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the allowance type.
     *
     * @return BelongsTo<AllowanceType, $this>
     */
    public function allowanceType(): BelongsTo
    {
        return $this->belongsTo(AllowanceType::class);
    }

    /**
     * Get the grade this allowance is for.
     *
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /**
     * Get the employee allowances.
     *
     * @return HasMany<EmployeeAllowance, $this>
     */
    public function employeeAllowances(): HasMany
    {
        return $this->hasMany(EmployeeAllowance::class);
    }

    /**
     * Get available frequency options.
     *
     * @return array<string, string>
     */
    public static function frequencyOptions(): array
    {
        return [
            self::FREQUENCY_MONTHLY => 'Monthly',
            self::FREQUENCY_ANNUAL => 'Annual',
            self::FREQUENCY_ONE_TIME => 'One-time',
        ];
    }

    /**
     * Check if this is a taxable allowance.
     */
    public function isTaxable(): bool
    {
        if ($this->allowanceType === null) {
            return true;
        }

        return $this->allowanceType->is_taxable;
    }

    /**
     * Calculate the monthly amount.
     */
    public function getMonthlyAmount(): float
    {
        return match ($this->frequency) {
            self::FREQUENCY_ANNUAL => (float) $this->amount / 12,
            self::FREQUENCY_ONE_TIME => 0, // One-time doesn't count as monthly
            default => (float) $this->amount,
        };
    }

    /**
     * Scope to only active allowances.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by allowance type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOfType($query, int $typeId)
    {
        return $query->where('allowance_type_id', $typeId);
    }

    /**
     * Scope to filter by grade.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForGrade($query, int $gradeId)
    {
        return $query->where('grade_id', $gradeId);
    }

    /**
     * Scope to filter by frequency.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWithFrequency($query, string $frequency)
    {
        return $query->where('frequency', $frequency);
    }
}
