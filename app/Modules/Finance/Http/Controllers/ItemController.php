<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Http\Requests\SaveItemRequest;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinItem;
use App\Modules\Finance\Support\Money;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ItemController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request): View
    {
        $tenantId = $this->allow($request, 'finance.view');

        return view('finance::items.index', [
            'items' => FinItem::query()->where('tenant_id', $tenantId)->orderBy('name')->get(),
            'incomeAccounts' => FinAccount::query()->where('tenant_id', $tenantId)->where('type', 'income')->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(SaveItemRequest $request): RedirectResponse
    {
        FinItem::query()->create([
            ...$request->safe()->except('unit_price'),
            'unit_price' => Money::toMinor($request->validated('unit_price')),
            'tenant_id' => $this->tenantId($request),
        ]);

        return back()->with('success', 'Item added.');
    }

    public function destroy(Request $request, string $item): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.invoices.manage');
        FinItem::query()->where('tenant_id', $tenantId)->where('uuid', $item)->firstOrFail()->update(['is_active' => false]);

        return back()->with('success', 'Item switched off.');
    }
}
