<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Http\Requests\SaveItemRequest;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinItem;
use App\Modules\Finance\Services\StockService;
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

    public function store(SaveItemRequest $request, StockService $stock): RedirectResponse
    {
        $tracked = $request->boolean('track_stock');
        $item = FinItem::query()->create([
            ...$request->safe()->except(['unit_price', 'track_stock', 'reorder_level', 'opening_quantity']),
            'unit_price' => Money::toMinor($request->validated('unit_price')),
            'track_stock' => $tracked,
            'reorder_level_thousandths' => $tracked ? (int) round(((float) $request->validated('reorder_level')) * 1000) : 0,
            'tenant_id' => $this->tenantId($request),
        ]);

        if ($tracked && (float) $request->validated('opening_quantity') > 0) {
            $stock->record($item, 'opening', (float) $request->validated('opening_quantity'), today(), null, 'Opening stock', $this->actorId($request));
        }

        return back()->with('success', 'Item added.');
    }

    public function destroy(Request $request, string $item): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.invoices.manage');
        FinItem::query()->where('tenant_id', $tenantId)->where('uuid', $item)->firstOrFail()->update(['is_active' => false]);

        return back()->with('success', 'Item switched off.');
    }
}
