<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Leave;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Holiday model - Public holidays that don't count against leave.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $name
 * @property Carbon $date
 * @property string|null $description
 * @property bool $is_recurring
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Holiday extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_holidays';

    protected $fillable = [
        'tenant_id',
        'name',
        'date',
        'description',
        'is_recurring',
        'is_active',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_recurring' => false,
        'is_active' => true,
    ];

    /**
     * Count holidays between two dates.
     */
    public static function countBetweenDates(Carbon $startDate, Carbon $endDate): int
    {
        $count = 0;
        $holidays = self::active()->get();

        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            foreach ($holidays as $holiday) {
                if ($holiday->isOnDate($current)) {
                    $count++;

                    break; // Only count once per day even if multiple holidays
                }
            }
            $current->addDay();
        }

        return $count;
    }

    /**
     * Get holiday dates between two dates as an array.
     *
     * @return array<Carbon>
     */
    public static function getDatesBetween(Carbon $startDate, Carbon $endDate): array
    {
        $dates = [];
        $holidays = self::active()->get();

        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            foreach ($holidays as $holiday) {
                if ($holiday->isOnDate($current)) {
                    $dates[] = $current->copy();

                    break;
                }
            }
            $current->addDay();
        }

        return $dates;
    }

    /**
     * Check if this holiday falls on a specific date.
     */
    public function isOnDate(Carbon $date): bool
    {
        if ($this->is_recurring) {
            // For recurring holidays, compare month and day only
            return $this->date->month === $date->month
                && $this->date->day === $date->day;
        }

        return $this->date->isSameDay($date);
    }

    /**
     * Scope to only active holidays.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to holidays in a date range.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeInDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate): void {
            // Non-recurring holidays in range
            $q->where('is_recurring', false)
                ->whereBetween('date', [$startDate, $endDate]);
        })->orWhere(function ($q) use ($startDate, $endDate): void {
            // Recurring holidays - check month/day combinations
            $q->where('is_recurring', true)
                ->where(function ($innerQ) use ($startDate, $endDate): void {
                    // This is a simplified approach - for production,
                    // you might want to calculate specific dates
                    $innerQ->whereRaw(
                        'EXTRACT(MONTH FROM date) * 100 + EXTRACT(DAY FROM date) BETWEEN ? AND ?',
                        [
                            $startDate->month * 100 + $startDate->day,
                            $endDate->month * 100 + $endDate->day,
                        ]
                    );
                });
        });
    }

    /**
     * Scope to holidays for a specific year.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForYear($query, int $year)
    {
        $startOfYear = Carbon::createFromDate($year, 1, 1);
        $endOfYear = Carbon::createFromDate($year, 12, 31);

        return $query->where(function ($q) use ($startOfYear, $endOfYear): void {
            $q->where('is_recurring', true)
                ->orWhereBetween('date', [$startOfYear, $endOfYear]);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_recurring' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
