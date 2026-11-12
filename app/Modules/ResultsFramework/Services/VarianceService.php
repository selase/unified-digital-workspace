<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Services;

use App\Models\User;
use App\Modules\ResultsFramework\Models\ResultIndicator;
use App\Modules\ResultsFramework\Models\ResultVarianceNote;
use DomainException;

final class VarianceService
{
    public function __construct(private IndicatorService $indicators) {}

    public function add(ResultIndicator $indicator, string $reason, ?string $action, ?string $userId): ResultVarianceNote
    {
        $viewer = $userId === null ? new User : User::query()->where('uuid', $userId)->firstOrFail();
        $reason = mb_trim($reason);
        if ($reason === '') {
            throw new DomainException('Enter the reason for the variance.');
        }
        if ($indicator->status !== 'active' || ! in_array($this->indicators->progress($indicator, $viewer, includePeriods: false)['status'], ['behind', 'missed'], true)) {
            throw new DomainException('A variance note can only be added to an active indicator that is behind or has missed its target.');
        }

        return $indicator->varianceNotes()->create([
            'tenant_id' => $indicator->tenant_id,
            'reason' => $reason,
            'corrective_action' => $action,
            'noted_by' => $userId,
        ]);
    }

    public function resolve(ResultVarianceNote $note, ?string $userId): void
    {
        $updated = ResultVarianceNote::query()->where('tenant_id', $note->tenant_id)->whereKey($note->id)
            ->where('status', 'open')->update(['status' => 'resolved', 'resolved_at' => now()]);

        if ($updated !== 1) {
            throw new DomainException('This variance note is already resolved.');
        }

        $note->refresh();
    }
}
