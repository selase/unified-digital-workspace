<?php

declare(strict_types=1);

namespace App\Modules\Volunteers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Services\StatementReports;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use App\Modules\Volunteers\Http\Requests\SaveVolunteerRequest;
use App\Modules\Volunteers\Models\VolHour;
use App\Modules\Volunteers\Models\VolOpportunity;
use App\Modules\Volunteers\Models\VolVolunteer;
use App\Modules\Volunteers\Services\VolunteerService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class VolunteerController extends Controller
{
    use ResolvesFinanceTenant;

    public function overview(Request $request, VolunteerService $volunteers, StatementReports $statements): View
    {
        $tenantId = $this->allow($request, 'volunteers.view');
        $to = CarbonImmutable::today();
        $from = $statements->fiscalYearStart($tenantId, $to);

        return view('volunteers::dashboard', [
            'summary' => $volunteers->summary($tenantId, $from, $to),
            'active' => VolVolunteer::query()->where('tenant_id', $tenantId)->where('status', 'active')->count(),
            'pending' => VolVolunteer::query()->where('tenant_id', $tenantId)->where('status', 'pending')->count(),
            'pendingHours' => VolHour::query()->where('tenant_id', $tenantId)->where('status', 'pending')->count(),
            'upcoming' => VolOpportunity::query()->where('tenant_id', $tenantId)->where('status', 'open')->whereDate('starts_on', '>=', today()->toDateString())->withCount('volunteers')->orderBy('starts_on')->limit(5)->get(),
            'from' => $from,
            'signupUrl' => route('volunteers.public.signup', ['tenant' => (string) \App\Models\Tenant::query()->whereKey($tenantId)->value('slug')]),
        ]);
    }

    public function index(Request $request): View
    {
        $tenantId = $this->allow($request, 'volunteers.view');
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();

        return view('volunteers::volunteers.index', [
            'volunteers' => VolVolunteer::query()->where('tenant_id', $tenantId)
                ->withSum(['hours as approved_minutes' => fn ($query) => $query->where('status', 'approved')], 'minutes')
                ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%")))
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->orderByRaw("case status when 'pending' then 0 when 'active' then 1 else 2 end")->orderBy('name')->paginate(25)->withQueryString(),
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function store(SaveVolunteerRequest $request, VolunteerService $volunteers): RedirectResponse
    {
        try {
            $volunteer = $volunteers->register($this->tenantId($request), (string) $request->validated('name'), $request->safe()->except('name'), true);
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('volunteers.volunteers.show', $volunteer->uuid)->with('success', 'Volunteer added.');
    }

    public function show(Request $request, string $volunteer): View
    {
        $tenantId = $this->allow($request, 'volunteers.view');
        $model = VolVolunteer::query()->where('tenant_id', $tenantId)->where('uuid', $volunteer)->firstOrFail();
        $hours = $model->hours()->with('opportunity')->latest('worked_on')->get();

        return view('volunteers::volunteers.show', [
            'volunteer' => $model,
            'hours' => $hours,
            'approvedMinutes' => (int) $hours->where('status', 'approved')->sum('minutes'),
            'opportunities' => VolOpportunity::query()->where('tenant_id', $tenantId)->where('status', 'open')->orderBy('starts_on')->get(),
        ]);
    }

    public function update(SaveVolunteerRequest $request, string $volunteer): RedirectResponse
    {
        VolVolunteer::query()->where('tenant_id', $this->tenantId($request))->where('uuid', $volunteer)->firstOrFail()->update($request->validated());

        return back()->with('success', 'Volunteer updated.');
    }

    public function status(Request $request, VolunteerService $volunteers, string $volunteer): RedirectResponse
    {
        $tenantId = $this->allow($request, 'volunteers.manage');
        $model = VolVolunteer::query()->where('tenant_id', $tenantId)->where('uuid', $volunteer)->firstOrFail();

        try {
            $volunteers->setStatus($model, (string) $request->input('status'));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Status updated.');
    }

    public function signup(Request $request, VolunteerService $volunteers, string $volunteer): RedirectResponse
    {
        $tenantId = $this->allow($request, 'volunteers.manage');
        $model = VolVolunteer::query()->where('tenant_id', $tenantId)->where('uuid', $volunteer)->firstOrFail();
        $opportunity = VolOpportunity::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->input('opportunity_id'));

        try {
            $volunteers->signUp($opportunity, $model);
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', $model->name.' signed up for '.$opportunity->title.'.');
    }
}
