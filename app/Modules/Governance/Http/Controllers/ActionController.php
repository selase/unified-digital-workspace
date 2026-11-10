<?php

declare(strict_types=1);

namespace App\Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Governance\Http\Requests\SaveActionRequest;
use App\Modules\Governance\Models\GovAction;
use App\Modules\Governance\Models\GovMeeting;
use App\Modules\Governance\Models\GovMember;
use App\Modules\Governance\Services\GovernanceService;
use App\Modules\Governance\Support\ResolvesGovernanceTenant;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ActionController extends Controller
{
    use ResolvesGovernanceTenant;

    public function index(Request $request): View
    {
        $tenantId = $this->allow($request, 'governance.view');
        $status = $request->string('status')->toString() ?: 'open';

        return view('governance::actions.index', [
            'actions' => GovAction::query()->where('tenant_id', $tenantId)->with('meeting')->when($status !== 'all', fn ($query) => $query->where('status', $status))->orderByRaw('due_on is null')->orderBy('due_on')->get(),
            'members' => GovMember::query()->where('tenant_id', $tenantId)->where('status', 'active')->orderBy('name')->get(),
            'meetings' => GovMeeting::query()->where('tenant_id', $tenantId)->where('status', 'held')->orderByDesc('meets_at')->limit(20)->get(),
            'status' => $status,
        ]);
    }

    public function store(SaveActionRequest $request, GovernanceService $governance): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $member = $request->validated('member_id') ? GovMember::query()->where('tenant_id', $tenantId)->find((int) $request->validated('member_id')) : null;
        $meeting = $request->input('meeting_id') ? GovMeeting::query()->where('tenant_id', $tenantId)->find((int) $request->input('meeting_id')) : null;

        try {
            $governance->addAction($tenantId, (string) $request->validated('description'), $meeting, $member, $request->validated('owner_name'), $request->validated('due_on') ? Carbon::parse($request->validated('due_on')) : null);
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Action added.');
    }

    public function toggle(Request $request, GovernanceService $governance, string $action): RedirectResponse
    {
        $tenantId = $this->allow($request, 'governance.manage');
        $model = GovAction::query()->where('tenant_id', $tenantId)->where('uuid', $action)->firstOrFail();
        $governance->setActionStatus($model, $model->status === 'open');

        return back()->with('success', $model->status === 'done' ? 'Action marked as done.' : 'Action reopened.');
    }
}
