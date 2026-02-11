<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Employees;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NextOfKin model - Employee next of kin information.
 *
 * @property int $id
 * @property string $uuid
 * @property int $employee_id
 * @property string $name
 * @property string|null $relationship
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class NextOfKin extends Model
{
    use HasHrmsUuid;
    use UsesTenantConnection;

    protected $table = 'hrms_next_of_kin';

    protected $fillable = [
        'employee_id',
        'name',
        'relationship',
        'phone',
        'email',
        'address',
    ];

    /**
     * Get the employee this next of kin belongs to.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
