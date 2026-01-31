<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasUuid;
use Spatie\Permission\Models\Role as SpatieRole;

final class Role extends SpatieRole
{
    use HasUuid;

    /**
     * Role names that are considered "System Roles" and cannot be deleted or modified by tenants.
     */
    public const SYSTEM_ROLES = [
        'Superadmin',
        'Org Superadmin',
        'Org Admin',
    ];

    protected $connection = 'landlord';

    /**
     * Determine if the role is a system role.
     */
    public function isSystemRole(): bool
    {
        return in_array($this->name, self::SYSTEM_ROLES) && $this->tenant_id === null;
    }
}
