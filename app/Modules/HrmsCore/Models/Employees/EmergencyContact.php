<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Employees;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EmergencyContact model - Employee emergency contact information.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $uuid
 * @property int $employee_id
 * @property string $name
 * @property string|null $relationship
 * @property string $phone
 * @property string|null $email
 * @property string|null $address
 * @property bool $is_primary
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class EmergencyContact extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_emergency_contacts';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'name',
        'relationship',
        'phone',
        'email',
        'address',
        'is_primary',
    ];

    /**
     * Get the employee this emergency contact belongs to.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope to only primary emergency contacts.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
