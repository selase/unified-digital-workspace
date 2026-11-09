<?php

declare(strict_types=1);

namespace App\Modules\Donations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Donations\Http\Requests\SaveDonorRequest;
use App\Modules\Donations\Models\DonDonor;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DonorController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'donations.view');
        $search = $request->string('search')->trim()->toString();

        return view('donations::donors.index', [
            'donors' => DonDonor::query()->where('tenant_id', $tenantId)
                ->withSum(['donations as given' => fn ($query) => $query->whereNull('voided_at')], 'amount')
                ->withCount(['donations as gifts' => fn ($query) => $query->whereNull('voided_at')])
                ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%")))
                ->orderBy('name')->paginate(25)->withQueryString(),
            'search' => $search,
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function store(SaveDonorRequest $request): RedirectResponse
    {
        $donor = DonDonor::query()->create([...$request->validated(), 'tenant_id' => $this->tenantId($request)]);

        return redirect()->route('donations.donors.show', $donor->uuid)->with('success', 'Donor added.');
    }

    public function show(Request $request, FinanceSettings $settings, string $donor): View
    {
        $tenantId = $this->allow($request, 'donations.view');
        $model = DonDonor::query()->where('tenant_id', $tenantId)->where('uuid', $donor)->firstOrFail();
        $gifts = $model->donations()->with(['campaign', 'fund'])->latest('date')->latest('id')->get();

        return view('donations::donors.show', [
            'donor' => $model,
            'gifts' => $gifts,
            'total' => (int) $gifts->whereNull('voided_at')->sum('amount'),
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function update(SaveDonorRequest $request, string $donor): RedirectResponse
    {
        DonDonor::query()->where('tenant_id', $this->tenantId($request))->where('uuid', $donor)->firstOrFail()->update($request->validated());

        return back()->with('success', 'Donor updated.');
    }
}
