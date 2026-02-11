<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\IncidentManagement\Models\Incident;

final class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('incidents.view') || $user->can('incidents.create');
    }

    public function view(User $user, Incident $incident): bool
    {
        if ($user->can('incidents.view')) {
            return true;
        }

        $userUuid = (string) $user->uuid;

        return $incident->reported_by_id === $userUuid
            || $incident->assigned_to_id === $userUuid
            || $incident->assignments()->where('assigned_to_id', $userUuid)->exists()
            || $incident->comments()->where('user_id', $userUuid)->exists();
    }

    public function update(User $user, Incident $incident): bool
    {
        $userUuid = (string) $user->uuid;

        return $user->can('incidents.update')
            || $incident->reported_by_id === $userUuid
            || $incident->assigned_to_id === $userUuid;
    }

    public function delete(User $user, Incident $incident): bool
    {
        return $user->can('incidents.delete');
    }
}
