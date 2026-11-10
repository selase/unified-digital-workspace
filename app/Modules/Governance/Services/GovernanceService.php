<?php

declare(strict_types=1);

namespace App\Modules\Governance\Services;

use App\Modules\Governance\Models\GovAction;
use App\Modules\Governance\Models\GovMeeting;
use App\Modules\Governance\Models\GovMember;
use App\Modules\Governance\Models\GovResolution;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The rules of a governance record: a meeting is held before it has minutes, minutes are approved once and then
 * locked, resolutions only exist for meetings that took place, and every action has an owner and a date.
 */
final class GovernanceService
{
    public const ATTENDANCE = ['present' => 'Present', 'apologies' => 'Apologies', 'absent' => 'Absent'];

    /**
     * @param  array{email?: string|null, phone?: string|null, position?: string|null, body?: string|null, term_starts_on?: string|null, term_ends_on?: string|null, notes?: string|null}  $details
     */
    public function addMember(string $tenantId, string $name, array $details): GovMember
    {
        if (isset($details['term_starts_on'], $details['term_ends_on']) && $details['term_ends_on'] < $details['term_starts_on']) {
            throw new DomainException('The term must end after it starts.');
        }

        return GovMember::query()->create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'email' => isset($details['email']) ? mb_strtolower((string) $details['email']) : null,
            'phone' => $details['phone'] ?? null,
            'position' => ($details['position'] ?? null) ?: 'Member',
            'body' => ($details['body'] ?? null) ?: 'Board',
            'term_starts_on' => $details['term_starts_on'] ?? null,
            'term_ends_on' => $details['term_ends_on'] ?? null,
            'notes' => $details['notes'] ?? null,
            'status' => 'active',
        ]);
    }

    public function setMemberStatus(GovMember $member, string $status): GovMember
    {
        if (! in_array($status, ['active', 'former'], true)) {
            throw new DomainException('Choose active or former.');
        }
        $member->forceFill(['status' => $status])->save();

        return $member;
    }

    public function schedule(string $tenantId, string $title, string $body, CarbonInterface $meetsAt, ?string $venue, ?int $quorum, ?string $agenda): GovMeeting
    {
        if ($quorum !== null && $quorum < 1) {
            throw new DomainException('The quorum must be at least 1.');
        }

        return GovMeeting::query()->create([
            'tenant_id' => $tenantId,
            'title' => $title,
            'body' => $body,
            'meets_at' => $meetsAt,
            'venue' => $venue,
            'quorum' => $quorum,
            'agenda' => $agenda,
            'status' => 'scheduled',
            'minutes_status' => 'none',
        ]);
    }

    public function saveAgenda(GovMeeting $meeting, ?string $agenda): GovMeeting
    {
        if ($meeting->status !== 'scheduled') {
            throw new DomainException('The agenda can only be changed before the meeting is held.');
        }
        $meeting->forceFill(['agenda' => $agenda])->save();

        return $meeting;
    }

    /**
     * @param  array<int, string>  $statuses  member id => present|apologies|absent
     */
    public function recordAttendance(GovMeeting $meeting, array $statuses): GovMeeting
    {
        if ($meeting->status === 'cancelled') {
            throw new DomainException('A cancelled meeting has no attendance.');
        }

        $valid = GovMember::query()->where('tenant_id', $meeting->tenant_id)->whereIn('id', array_keys($statuses))->pluck('id')->all();
        $sync = [];
        foreach ($statuses as $memberId => $status) {
            if (! in_array((int) $memberId, $valid, true) || ! array_key_exists($status, self::ATTENDANCE)) {
                throw new DomainException('Attendance contains someone who is not a member, or an unknown status.');
            }
            $sync[(int) $memberId] = ['status' => $status];
        }
        $meeting->members()->sync($sync);

        return $meeting;
    }

    public function presentCount(GovMeeting $meeting): int
    {
        return $meeting->members()->wherePivot('status', 'present')->count();
    }

    /** True or false once a quorum is set; null when the meeting has no quorum rule. */
    public function quorumMet(GovMeeting $meeting): ?bool
    {
        return $meeting->quorum === null ? null : $this->presentCount($meeting) >= $meeting->quorum;
    }

    public function markHeld(GovMeeting $meeting): GovMeeting
    {
        if ($meeting->status !== 'scheduled') {
            throw new DomainException('Only a scheduled meeting can be marked as held.');
        }
        if ($meeting->meets_at->isFuture() && ! $meeting->meets_at->isToday()) {
            throw new DomainException('The meeting is in the future, so it cannot be marked as held yet.');
        }
        if ($this->presentCount($meeting) === 0) {
            throw new DomainException('Record who attended before marking the meeting as held.');
        }

        $meeting->forceFill(['status' => 'held', 'minutes_status' => 'draft'])->save();

        return $meeting;
    }

    public function cancel(GovMeeting $meeting): GovMeeting
    {
        if ($meeting->status !== 'scheduled') {
            throw new DomainException('Only a scheduled meeting can be cancelled.');
        }
        $meeting->forceFill(['status' => 'cancelled'])->save();

        return $meeting;
    }

    public function saveMinutes(GovMeeting $meeting, string $minutes): GovMeeting
    {
        if ($meeting->status !== 'held') {
            throw new DomainException('Minutes can only be written for a meeting that was held.');
        }
        if ($meeting->minutes_status === 'approved') {
            throw new DomainException('Approved minutes are locked. Record any correction as a resolution at the next meeting.');
        }
        $meeting->forceFill(['minutes' => $minutes, 'minutes_status' => 'draft'])->save();

        return $meeting;
    }

    public function approveMinutes(GovMeeting $meeting, CarbonInterface $date): GovMeeting
    {
        if ($meeting->status !== 'held' || $meeting->minutes_status !== 'draft' || mb_trim((string) $meeting->minutes) === '') {
            throw new DomainException('Only written, unapproved minutes of a held meeting can be approved.');
        }
        if ($date->lt($meeting->meets_at->copy()->startOfDay())) {
            throw new DomainException('Minutes cannot be approved before the meeting date.');
        }

        $meeting->forceFill(['minutes_status' => 'approved', 'minutes_approved_on' => $date->toDateString()])->save();

        return $meeting;
    }

    public function addResolution(GovMeeting $meeting, string $text, string $outcome): GovResolution
    {
        if ($meeting->status !== 'held') {
            throw new DomainException('Resolutions can only be recorded for a meeting that was held.');
        }
        if (! array_key_exists($outcome, GovResolution::OUTCOMES)) {
            throw new DomainException('Choose passed, rejected or deferred.');
        }

        return DB::connection('tenant')->transaction(function () use ($meeting, $text, $outcome): GovResolution {
            $year = $meeting->meets_at->format('Y');
            $last = GovResolution::query()->where('tenant_id', $meeting->tenant_id)->where('reference', 'like', "R-{$year}-%")->lockForUpdate()->orderByDesc('reference')->value('reference');
            $next = $last === null ? 1 : ((int) mb_substr((string) $last, -3)) + 1;

            return GovResolution::query()->create([
                'tenant_id' => $meeting->tenant_id,
                'meeting_id' => $meeting->id,
                'reference' => sprintf('R-%s-%03d', $year, $next),
                'text' => $text,
                'outcome' => $outcome,
            ]);
        });
    }

    public function addAction(string $tenantId, string $description, ?GovMeeting $meeting, ?GovMember $member, ?string $ownerName, ?CarbonInterface $dueOn): GovAction
    {
        if (! $member instanceof GovMember && mb_trim((string) $ownerName) === '') {
            throw new DomainException('Say who is responsible for this action.');
        }

        return GovAction::query()->create([
            'tenant_id' => $tenantId,
            'meeting_id' => $meeting?->id,
            'member_id' => $member?->id,
            'description' => $description,
            'owner_name' => $member instanceof GovMember ? $member->name : $ownerName,
            'due_on' => $dueOn?->toDateString(),
            'status' => 'open',
        ]);
    }

    public function setActionStatus(GovAction $action, bool $done): GovAction
    {
        $action->forceFill(['status' => $done ? 'done' : 'open', 'completed_on' => $done ? today()->toDateString() : null])->save();

        return $action;
    }

    /**
     * Members whose term ends within the number of days, soonest first.
     *
     * @return Collection<int, GovMember>
     */
    public function termsEnding(string $tenantId, int $days = 90): Collection
    {
        return GovMember::query()->where('tenant_id', $tenantId)->where('status', 'active')->whereNotNull('term_ends_on')
            ->whereDate('term_ends_on', '<=', today()->addDays($days)->toDateString())->orderBy('term_ends_on')->get();
    }
}
