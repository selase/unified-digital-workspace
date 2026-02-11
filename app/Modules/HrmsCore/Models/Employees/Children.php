<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Employees;

use App\Modules\HrmsCore\Enums\Gender;
use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Children model - Employee children records.
 *
 * @property int $id
 * @property string $uuid
 * @property int $employee_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $date_of_birth
 * @property string|null $gender
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class Children extends Model
{
    use HasHrmsUuid;
    use UsesTenantConnection;

    protected $table = 'hrms_children';

    protected $fillable = [
        'employee_id',
        'name',
        'date_of_birth',
        'gender',
    ];

    /**
     * Get the employee this child belongs to.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'gender' => Gender::class,
        ];
    }
}
