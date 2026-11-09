<?php

declare(strict_types=1);

namespace App\Modules\Volunteers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use App\Modules\Volunteers\Http\Requests\LogHoursRequest;
use App\Modules\Volunteers\Models\VolHour;
use App\Modules\Volunteers\Models\VolOpportunity;
use App\Modules\Volunteers\Models\VolVolunteer;
use App\Modules\Volunteers\Services\VolunteerService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class HoursController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request): View
    {
        $tenantId = $this->allow($request, 'volunteers.view');
        $status = $request->string('status', 'pending')->toString();

        return view('volunteers::hours.index', [
            'hours' => VolHour::query()->where('tenant_id', $tenantId)->with(['volunteer', 'opportunity'])
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->latest('worked_on')->latest('id')->paginate(25)->withQueryString(),
            'status' => $status,
            'volunteers' => VolVolunteer::query()->where('tenant_id', $tenantId)->where('status', 'active')->orderBy('name')->get(),
            'opportunities' => VolOpportunity::query()->where('tenant_id', $tenantId)->orderByDesc('starts_on')->limit(30)->get(),
        ]);
    }

    public function store(LogHoursRequest $request, VolunteerService $volunteers): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $volunteer = VolVolunteer::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->validated('volunteer_id'));
        $opportunity = $request->validated('opportunity_id') ? VolOpportunity::query()->where('tenant_id', $tenantId)->find((int) $request->validated('opportunity_id')) : null;

        try {
            $volunteers->logHours($volunteer, Carbon::parse($request->validated('worked_on')), (float) $request->validated('hours'), $request->validated('activity'), $opportunity);
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Hours logged, waiting for approval.');
    }

    public function review(Request $request, VolunteerService $volunteers, string $hour): RedirectResponse
    {
        $tenantId = $this->allow($request, 'volunteers.hours.approve');
        $model = VolHour::query()->where('tenant_id', $tenantId)->where('uuid', $hour)->firstOrFail();

        try {
            $volunteers->review($model, $request->input('decision') === 'approve', $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', $request->input('decision') === 'approve' ? 'Hours approved.' : 'Hours rejected.');
    }
}
