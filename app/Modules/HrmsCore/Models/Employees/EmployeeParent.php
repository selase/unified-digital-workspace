<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Employees;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EmployeeParent model - Employee parent information.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $uuid
 * @property int $employee_id
 * @property string|null $father_name
 * @property bool $father_alive
 * @property string|null $father_occupation
 * @property string|null $mother_name
 * @property bool $mother_alive
 * @property string|null $mother_occupation
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class EmployeeParent extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_employee_parents';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'father_name',
        'father_alive',
        'father_occupation',
        'mother_name',
        'mother_alive',
        'mother_occupation',
    ];

    /**
     * Get the employee this parent info belongs to.
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
            'father_alive' => 'boolean',
            'mother_alive' => 'boolean',
        ];
    }
}
