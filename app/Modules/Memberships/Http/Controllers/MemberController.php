<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Services\OnlinePaymentService;
use App\Modules\Finance\Services\StatementReports;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use App\Modules\Memberships\Http\Requests\SaveMemberRequest;
use App\Modules\Memberships\Models\MemMember;
use App\Modules\Memberships\Models\MemType;
use App\Modules\Memberships\Services\MembershipService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class MemberController extends Controller
{
    use ResolvesFinanceTenant;

    public function overview(Request $request, MembershipService $memberships, StatementReports $statements, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'memberships.view');
        $memberships->applyPayments($tenantId);
        $from = $statements->fiscalYearStart($tenantId, CarbonImmutable::today());

        return view('memberships::dashboard', [
            'summary' => $memberships->summary($tenantId, $from),
            'renewals' => MemMember::query()->where('tenant_id', $tenantId)->where('status', 'active')->whereDate('expires_on', '<=', today()->addDays(60)->toDateString())->with('type')->orderBy('expires_on')->limit(8)->get(),
            'currency' => $settings->for($tenantId)->base_currency,
            'joinUrl' => route('memberships.public.join', ['tenant' => (string) Tenant::query()->whereKey($tenantId)->value('slug')]),
        ]);
    }

    public function index(Request $request, MembershipService $memberships): View
    {
        $tenantId = $this->allow($request, 'memberships.view');
        $memberships->applyPayments($tenantId);
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();

        return view('memberships::members.index', [
            'members' => MemMember::query()->where('tenant_id', $tenantId)->with('type')
                ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%")->orWhere('number', 'ilike', "%{$search}%")))
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->orderBy('name')->paginate(25)->withQueryString(),
            'types' => MemType::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(),
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function store(SaveMemberRequest $request, MembershipService $memberships): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $type = MemType::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->validated('type_id'));

        try {
            $member = $memberships->join($tenantId, $type, (string) $request->validated('name'), $request->safe()->only(['email', 'phone', 'notes']), $this->actorId($request));
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('memberships.members.show', $member->uuid)->with('success', 'Member added.');
    }

    public function show(Request $request, MembershipService $memberships, OnlinePaymentService $online, string $member): View
    {
        $tenantId = $this->allow($request, 'memberships.view');
        $memberships->applyPayments($tenantId);
        $model = MemMember::query()->where('tenant_id', $tenantId)->where('uuid', $member)->with(['type', 'contact'])->firstOrFail();
        $renewals = $model->renewals()->with('invoice')->latest('period_start')->get();
        $open = $renewals->firstWhere('status', 'billed');

        return view('memberships::members.show', [
            'member' => $model,
            'renewals' => $renewals,
            'types' => MemType::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(),
            'payUrl' => $open?->invoice && $online->canCollect($open->invoice) ? $online->paymentUrl($open->invoice) : null,
        ]);
    }

    public function update(SaveMemberRequest $request, MembershipService $memberships, string $member): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $model = MemMember::query()->where('tenant_id', $tenantId)->where('uuid', $member)->firstOrFail();
        $model->update($request->safe()->only(['name', 'email', 'phone', 'notes']));

        if ((int) $request->validated('type_id') !== $model->type_id) {
            try {
                $memberships->changeType($model, MemType::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->validated('type_id')));
            } catch (DomainException $error) {
                return back()->with('error', $error->getMessage());
            }
        }

        return back()->with('success', 'Member updated.');
    }

    public function bill(Request $request, MembershipService $memberships, string $member): RedirectResponse
    {
        return $this->act($request, $member, fn (MemMember $model) => $memberships->billDues($model, $this->actorId($request)), 'Dues invoice raised.');
    }

    public function complimentary(Request $request, MembershipService $memberships, string $member): RedirectResponse
    {
        return $this->act($request, $member, fn (MemMember $model) => $memberships->grantComplimentary($model), 'Membership activated without an invoice.');
    }

    public function cancel(Request $request, MembershipService $memberships, string $member): RedirectResponse
    {
        return $this->act($request, $member, fn (MemMember $model) => $memberships->cancel($model, $this->actorId($request)), 'Membership cancelled.');
    }

    public function reinstate(Request $request, MembershipService $memberships, string $member): RedirectResponse
    {
        return $this->act($request, $member, fn (MemMember $model) => $memberships->reinstate($model), 'Membership reinstated.');
    }

    private function act(Request $request, string $member, callable $action, string $message): RedirectResponse
    {
        $tenantId = $this->allow($request, 'memberships.manage');
        $model = MemMember::query()->where('tenant_id', $tenantId)->where('uuid', $member)->with(['type', 'contact'])->firstOrFail();

        try {
            $action($model);
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', $message);
    }
}
