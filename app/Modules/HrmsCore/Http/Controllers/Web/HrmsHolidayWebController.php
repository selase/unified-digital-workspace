<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\HrmsCore\Http\Requests\HolidayRequest;
use App\Modules\HrmsCore\Models\Leave\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class HrmsHolidayWebController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user || (! $user->can('hrms.leave.view') && ! $user->can('hrms.leave.manage_holidays'))) {
            abort(403);
        }

        $query = Holiday::query();

        if ($request->filled('year')) {
            $year = (int) $request->input('year');
            $query->forYear($year);
        }

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $holidays = $query->orderBy('date', 'asc')->paginate(20)->withQueryString();

        return view('hrms-core::holidays-index', [
            'holidays' => $holidays,
            'currentYear' => (int) ($request->input('year', date('Y'))),
        ]);
    }

    public function store(HolidayRequest $request): RedirectResponse
    {
        $user = $request->user();
        $activeTenantId = (string) (session('active_tenant_id') ?? $user?->tenant_id);

        $validated = $request->validated();
        $validated['is_recurring'] = $request->boolean('is_recurring');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['tenant_id'] = $activeTenantId;

        Holiday::create($validated);

        return redirect()
            ->route('hrms-core.holidays.index')
            ->with('status', 'success')
            ->with('message', 'Public holiday added successfully.');
    }

    public function update(HolidayRequest $request, Holiday $holiday): RedirectResponse
    {
        $this->authorizeHolidayTenant($request, $holiday);

        $validated = $request->validated();
        $validated['is_recurring'] = $request->boolean('is_recurring');
        $validated['is_active'] = $request->boolean('is_active', true);

        $holiday->update($validated);

        return redirect()
            ->route('hrms-core.holidays.index')
            ->with('status', 'success')
            ->with('message', 'Public holiday updated successfully.');
    }

    public function destroy(Request $request, Holiday $holiday): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->can('hrms.leave.manage_holidays')) {
            abort(403);
        }

        $this->authorizeHolidayTenant($request, $holiday);

        $holiday->delete();

        return redirect()
            ->route('hrms-core.holidays.index')
            ->with('status', 'success')
            ->with('message', 'Public holiday deleted successfully.');
    }

    private function authorizeHolidayTenant(Request $request, Holiday $holiday): void
    {
        $user = $request->user();
        $activeTenantId = (string) (session('active_tenant_id') ?? $user?->tenant_id);

        if ($activeTenantId !== '' && (string) $holiday->tenant_id !== $activeTenantId) {
            abort(404, 'Holiday not found in active organization.');
        }
    }
}
