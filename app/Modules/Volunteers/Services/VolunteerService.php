<?php

declare(strict_types=1);

namespace App\Modules\Volunteers\Services;

use App\Modules\Volunteers\Models\VolHour;
use App\Modules\Volunteers\Models\VolOpportunity;
use App\Modules\Volunteers\Models\VolVolunteer;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

final class VolunteerService
{
    /**
     * @param  array{email?: string|null, phone?: string|null, skills?: string|null, emergency_contact?: string|null, notes?: string|null}  $details
     */
    public function register(string $tenantId, string $name, array $details, bool $approved): VolVolunteer
    {
        $email = isset($details['email']) ? mb_strtolower((string) $details['email']) : null;

        if ($email !== null && VolVolunteer::query()->where('tenant_id', $tenantId)->where('email', $email)->exists()) {
            throw new DomainException('A volunteer with this email address is already registered.');
        }

        return VolVolunteer::query()->create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'email' => $email,
            'phone' => $details['phone'] ?? null,
            'skills' => $details['skills'] ?? null,
            'emergency_contact' => $details['emergency_contact'] ?? null,
            'notes' => $details['notes'] ?? null,
            'status' => $approved ? 'active' : 'pending',
            'joined_on' => $approved ? today() : null,
        ]);
    }

    public function setStatus(VolVolunteer $volunteer, string $status): VolVolunteer
    {
        if (! array_key_exists($status, VolVolunteer::STATUSES) || $status === 'pending') {
            throw new DomainException('Choose active or inactive.');
        }

        $volunteer->forceFill(['status' => $status, 'joined_on' => $volunteer->joined_on ?? today()])->save();

        return $volunteer;
    }

    public function logHours(VolVolunteer $volunteer, CarbonInterface $date, float $hours, ?string $activity = null, ?VolOpportunity $opportunity = null): VolHour
    {
        if ($volunteer->status !== 'active') {
            throw new DomainException('Hours can only be logged for an active volunteer.');
        }
        if ($hours <= 0 || $hours > 24) {
            throw new DomainException('Hours must be more than 0 and no more than 24.');
        }
        if ($date->gt(today())) {
            throw new DomainException('Hours cannot be logged for a future date.');
        }

        $minutes = (int) round($hours * 60);
        $sameDay = (int) VolHour::query()->where('volunteer_id', $volunteer->id)->whereDate('worked_on', $date->toDateString())->where('status', '!=', 'rejected')->sum('minutes');
        if ($sameDay + $minutes > 24 * 60) {
            throw new DomainException('That is more than 24 hours for one day.');
        }

        return VolHour::query()->create([
            'tenant_id' => $volunteer->tenant_id,
            'volunteer_id' => $volunteer->id,
            'opportunity_id' => $opportunity?->id,
            'worked_on' => $date->toDateString(),
            'minutes' => $minutes,
            'activity' => $activity,
            'status' => 'pending',
        ]);
    }

    public function review(VolHour $hour, bool $approve, ?string $reviewerUuid): VolHour
    {
        if ($hour->status !== 'pending') {
            throw new DomainException('Only pending hours can be reviewed.');
        }

        $hour->forceFill(['status' => $approve ? 'approved' : 'rejected', 'reviewed_by' => $reviewerUuid, 'reviewed_at' => now()])->save();

        return $hour;
    }

    public function signUp(VolOpportunity $opportunity, VolVolunteer $volunteer): void
    {
        if ($opportunity->status !== 'open') {
            throw new DomainException('This opportunity is closed.');
        }
        if ($volunteer->status !== 'active') {
            throw new DomainException('Only active volunteers can sign up.');
        }

        DB::connection('tenant')->transaction(function () use ($opportunity, $volunteer): void {
            $locked = VolOpportunity::query()->lockForUpdate()->findOrFail($opportunity->id);
            if ($locked->volunteers()->whereKey($volunteer->id)->exists()) {
                throw new DomainException('This volunteer is already signed up.');
            }
            if ($locked->isFull()) {
                throw new DomainException('All places are taken.');
            }
            $locked->volunteers()->attach($volunteer->id);
        });
    }

    /**
     * Approved hours between two dates, per volunteer, most first.
     *
     * @return array{total_minutes: int, volunteers: int, top: list<array{volunteer: VolVolunteer, minutes: int}>}
     */
    public function summary(string $tenantId, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = VolHour::query()->where('tenant_id', $tenantId)->where('status', 'approved')
            ->whereDate('worked_on', '>=', $from->toDateString())->whereDate('worked_on', '<=', $to->toDateString())
            ->selectRaw('volunteer_id, SUM(minutes) as minutes')->groupBy('volunteer_id')->orderByDesc('minutes')->get();

        $volunteers = VolVolunteer::query()->where('tenant_id', $tenantId)->whereIn('id', $rows->pluck('volunteer_id'))->get()->keyBy('id');

        return [
            'total_minutes' => (int) $rows->sum('minutes'),
            'volunteers' => $rows->count(),
            'top' => $rows->take(5)->map(fn ($row): array => ['volunteer' => $volunteers->get($row->volunteer_id), 'minutes' => (int) $row->minutes])->all(),
        ];
    }
}
