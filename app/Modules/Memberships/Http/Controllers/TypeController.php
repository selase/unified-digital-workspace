<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Support\Money;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use App\Modules\Memberships\Http\Requests\SaveTypeRequest;
use App\Modules\Memberships\Models\MemType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TypeController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'memberships.view');

        return view('memberships::types.index', [
            'types' => MemType::query()->where('tenant_id', $tenantId)->withCount(['members as active_count' => fn ($query) => $query->where('status', 'active')])->orderBy('name')->get(),
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function store(SaveTypeRequest $request): RedirectResponse
    {
        MemType::query()->create($this->payload($request) + ['tenant_id' => $this->tenantId($request)]);

        return back()->with('success', 'Membership type created.');
    }

    public function update(SaveTypeRequest $request, string $type): RedirectResponse
    {
        MemType::query()->where('tenant_id', $this->tenantId($request))->where('uuid', $type)->firstOrFail()->update($this->payload($request));

        return back()->with('success', 'Membership type updated. Existing invoices keep the fee they were raised at.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(SaveTypeRequest $request): array
    {
        $data = $request->validated();

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'fee' => Money::toMinor($data['fee']),
            'period_months' => (int) $data['period_months'],
            'is_public' => $request->boolean('is_public'),
            'auto_invoice' => $request->boolean('auto_invoice'),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
