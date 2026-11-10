<?php

declare(strict_types=1);

namespace App\Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Governance\Http\Requests\SaveMemberRequest;
use App\Modules\Governance\Models\GovMember;
use App\Modules\Governance\Services\GovernanceService;
use App\Modules\Governance\Support\ResolvesGovernanceTenant;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class MemberController extends Controller
{
    use ResolvesGovernanceTenant;

    public function index(Request $request, GovernanceService $governance): View
    {
        $tenantId = $this->allow($request, 'governance.view');

        return view('governance::members.index', [
            'members' => GovMember::query()->where('tenant_id', $tenantId)->orderByRaw("case status when 'active' then 0 else 1 end")->orderBy('body')->orderBy('name')->get(),
            'ending' => $governance->termsEnding($tenantId)->pluck('id')->all(),
            'positions' => GovMember::POSITIONS,
        ]);
    }

    public function store(SaveMemberRequest $request, GovernanceService $governance): RedirectResponse
    {
        try {
            $governance->addMember($this->tenantId($request), (string) $request->validated('name'), $request->safe()->except('name'));
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Member added.');
    }

    public function status(Request $request, GovernanceService $governance, string $member): RedirectResponse
    {
        $tenantId = $this->allow($request, 'governance.manage');
        $model = GovMember::query()->where('tenant_id', $tenantId)->where('uuid', $member)->firstOrFail();

        try {
            $governance->setMemberStatus($model, (string) $request->input('status'));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Member updated.');
    }
}
