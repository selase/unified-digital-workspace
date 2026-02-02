<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Employees;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BankDetails model - Employee bank account information.
 *
 * @property int $id
 * @property string $uuid
 * @property int $employee_id
 * @property string $bank_name
 * @property string|null $branch_name
 * @property string $account_number
 * @property string|null $account_type
 * @property string|null $sort_code
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class BankDetails extends Model
{
    use HasHrmsUuid;

    protected $table = 'hrms_bank_details';

    protected $connection = 'landlord';

    protected $fillable = [
        'employee_id',
        'bank_name',
        'branch_name',
        'account_number',
        'account_type',
        'sort_code',
    ];

    /**
     * Get the employee these bank details belong to.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
