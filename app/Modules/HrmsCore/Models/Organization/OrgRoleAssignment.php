<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Organization;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $assignable_type
 * @property int $assignable_id
 * @property int|null $role_id
 * @property string $role_name
 * @property string|null $assigned_by_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class OrgRoleAssignment extends Model
{
    use BelongsToTenant;

    protected $table = 'hrms_org_role_assignments';

    protected $fillable = [
        'tenant_id',
        'assignable_type',
        'assignable_id',
        'role_id',
        'role_name',
        'assigned_by_id',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id', 'uuid');
    }

    protected static function booted(): void
    {
        self::saved(function (OrgRoleAssignment $assignment): void {
            if ($assignment->tenant_id) {
                \App\Services\Auth\OrgPermissionResolver::flushForTenant((string) $assignment->tenant_id);
            }
        });

        self::deleted(function (OrgRoleAssignment $assignment): void {
            if ($assignment->tenant_id) {
                \App\Services\Auth\OrgPermissionResolver::flushForTenant((string) $assignment->tenant_id);
            }
        });
    }
}
