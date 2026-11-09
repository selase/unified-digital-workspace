<?php

declare(strict_types=1);

namespace App\Modules\Grants\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use App\Modules\Grants\Http\Requests\SaveFunderRequest;
use App\Modules\Grants\Models\GrtFunder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class FunderController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'grants.view');

        return view('grants::funders.index', [
            'funders' => GrtFunder::query()->where('tenant_id', $tenantId)
                ->withCount('grants')->withSum(['grants as awarded' => fn ($query) => $query->whereIn('status', ['awarded', 'active', 'closed'])], 'total_amount')
                ->orderBy('name')->get(),
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function store(SaveFunderRequest $request): RedirectResponse
    {
        GrtFunder::query()->create([...$request->validated(), 'tenant_id' => $this->tenantId($request)]);

        return back()->with('success', 'Funder added.');
    }

    public function update(SaveFunderRequest $request, string $funder): RedirectResponse
    {
        GrtFunder::query()->where('tenant_id', $this->tenantId($request))->where('uuid', $funder)->firstOrFail()->update($request->validated());

        return back()->with('success', 'Funder updated.');
    }
}
