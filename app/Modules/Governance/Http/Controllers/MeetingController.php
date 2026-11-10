<?php

declare(strict_types=1);

namespace App\Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\Governance\Http\Requests\SaveMeetingRequest;
use App\Modules\Governance\Models\GovAction;
use App\Modules\Governance\Models\GovMeeting;
use App\Modules\Governance\Models\GovMember;
use App\Modules\Governance\Models\GovResolution;
use App\Modules\Governance\Services\GovernanceService;
use App\Modules\Governance\Support\ResolvesGovernanceTenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class MeetingController extends Controller
{
    use ResolvesGovernanceTenant;

    public function overview(Request $request, GovernanceService $governance): View
    {
        $tenantId = $this->allow($request, 'governance.view');

        return view('governance::dashboard', [
            'upcoming' => GovMeeting::query()->where('tenant_id', $tenantId)->where('status', 'scheduled')->where('meets_at', '>=', now()->startOfDay())->orderBy('meets_at')->limit(5)->get(),
            'unapproved' => GovMeeting::query()->where('tenant_id', $tenantId)->where('status', 'held')->where('minutes_status', '!=', 'approved')->orderBy('meets_at')->get(),
            'openActions' => GovAction::query()->where('tenant_id', $tenantId)->where('status', 'open')->orderByRaw('due_on is null')->orderBy('due_on')->limit(8)->get(),
            'overdue' => GovAction::query()->where('tenant_id', $tenantId)->where('status', 'open')->whereDate('due_on', '<', today()->toDateString())->count(),
            'ending' => $governance->termsEnding($tenantId),
            'activeMembers' => GovMember::query()->where('tenant_id', $tenantId)->where('status', 'active')->count(),
        ]);
    }

    public function index(Request $request): View
    {
        $tenantId = $this->allow($request, 'governance.view');

        return view('governance::meetings.index', [
            'meetings' => GovMeeting::query()->where('tenant_id', $tenantId)->orderByDesc('meets_at')->get(),
            'bodies' => GovMeeting::BODIES,
        ]);
    }

    public function store(SaveMeetingRequest $request, GovernanceService $governance): RedirectResponse
    {
        try {
            $meeting = $governance->schedule(
                $this->tenantId($request),
                (string) $request->validated('title'),
                (string) ($request->validated('body') ?: 'Board'),
                Carbon::parse($request->validated('meets_at')),
                $request->validated('venue'),
                $request->validated('quorum') ? (int) $request->validated('quorum') : null,
                $request->validated('agenda'),
            );
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('governance.meetings.show', $meeting->uuid)->with('success', 'Meeting scheduled.');
    }

    public function show(Request $request, GovernanceService $governance, string $meeting): View
    {
        $tenantId = $this->allow($request, 'governance.view');
        $model = $this->find($tenantId, $meeting);
        $members = GovMember::query()->where('tenant_id', $tenantId)->where('status', 'active')->orderBy('name')->get();

        return view('governance::meetings.show', [
            'meeting' => $model,
            'members' => $members,
            'attendance' => $model->members()->get()->mapWithKeys(fn (GovMember $member): array => [$member->id => $member->pivot->status])->all(),
            'present' => $governance->presentCount($model),
            'quorumMet' => $governance->quorumMet($model),
            'resolutions' => $model->resolutions()->get(),
            'actions' => $model->actions()->get(),
            'attendanceLabels' => GovernanceService::ATTENDANCE,
            'outcomes' => GovResolution::OUTCOMES,
        ]);
    }

    public function agenda(Request $request, GovernanceService $governance, string $meeting): RedirectResponse
    {
        return $this->act($request, $meeting, 'governance.manage', fn (GovMeeting $model) => $governance->saveAgenda($model, $request->input('agenda')), 'Agenda saved.');
    }

    public function attendance(Request $request, GovernanceService $governance, string $meeting): RedirectResponse
    {
        return $this->act($request, $meeting, 'governance.manage', fn (GovMeeting $model) => $governance->recordAttendance($model, array_map('strval', (array) $request->input('attendance', []))), 'Attendance saved.');
    }

    public function hold(Request $request, GovernanceService $governance, string $meeting): RedirectResponse
    {
        return $this->act($request, $meeting, 'governance.manage', fn (GovMeeting $model) => $governance->markHeld($model), 'Meeting marked as held. You can now write the minutes.');
    }

    public function cancel(Request $request, GovernanceService $governance, string $meeting): RedirectResponse
    {
        return $this->act($request, $meeting, 'governance.manage', fn (GovMeeting $model) => $governance->cancel($model), 'Meeting cancelled.');
    }

    public function minutes(Request $request, GovernanceService $governance, string $meeting): RedirectResponse
    {
        $request->validate(['minutes' => ['required', 'string', 'max:50000']], ['minutes.required' => 'Write the minutes first.']);

        return $this->act($request, $meeting, 'governance.manage', fn (GovMeeting $model) => $governance->saveMinutes($model, (string) $request->input('minutes')), 'Minutes saved as a draft.');
    }

    public function approveMinutes(Request $request, GovernanceService $governance, string $meeting): RedirectResponse
    {
        return $this->act($request, $meeting, 'governance.minutes.approve', fn (GovMeeting $model) => $governance->approveMinutes($model, Carbon::parse((string) $request->input('date', today()->toDateString()))), 'Minutes approved and locked.');
    }

    public function resolution(Request $request, GovernanceService $governance, string $meeting): RedirectResponse
    {
        $request->validate(['text' => ['required', 'string', 'max:2000'], 'outcome' => ['required', 'string']], ['text.required' => 'Write the resolution.']);

        return $this->act($request, $meeting, 'governance.manage', fn (GovMeeting $model) => $governance->addResolution($model, (string) $request->input('text'), (string) $request->input('outcome')), 'Resolution recorded.');
    }

    /** The meeting pack: agenda, attendance, minutes, resolutions and actions in one printable PDF. */
    public function pack(Request $request, GovernanceService $governance, string $meeting): Response
    {
        $tenantId = $this->allow($request, 'governance.view');
        $model = $this->find($tenantId, $meeting);

        $output = Pdf::loadView('governance::pdf.pack', [
            'meeting' => $model,
            'organisation' => (string) Tenant::query()->whereKey($tenantId)->value('name'),
            'attendance' => $model->members()->orderBy('name')->get(),
            'present' => $governance->presentCount($model),
            'quorumMet' => $governance->quorumMet($model),
            'resolutions' => $model->resolutions()->get(),
            'actions' => $model->actions()->get(),
        ])->setPaper('a4')->output();

        return response($output, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="meeting-pack-'.$model->meets_at->format('Y-m-d').'.pdf"']);
    }

    private function act(Request $request, string $meeting, string $permission, callable $action, string $message): RedirectResponse
    {
        $tenantId = $this->allow($request, $permission);

        try {
            $action($this->find($tenantId, $meeting));
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return back()->with('success', $message);
    }

    private function find(string $tenantId, string $uuid): GovMeeting
    {
        return GovMeeting::query()->where('tenant_id', $tenantId)->where('uuid', $uuid)->firstOrFail();
    }
}
