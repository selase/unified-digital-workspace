<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\FinItem;
use App\Modules\Finance\Models\FinStockMovement;
use App\Modules\Finance\Services\ReportCsv;
use App\Modules\Finance\Services\StockService;
use App\Modules\Finance\Support\Money;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StockController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request, StockService $stock, ReportCsv $csv): View|StreamedResponse
    {
        $tenantId = $this->allow($request, 'finance.view');
        $levels = $stock->levels($tenantId);

        if ($request->query('export') === 'csv') {
            return $csv->download('stock-levels-'.today()->toDateString().'.csv', [
                ['Stock levels', 'As at '.today()->toDateString()],
                ['Item', 'SKU', 'On hand', 'Reorder at', 'Status'],
                ...$levels->map(fn (array $row): array => [$row['item']->name, $row['item']->sku, $row['on_hand'] / 1000, $row['item']->reorder_level_thousandths / 1000, $row['low'] ? 'Reorder' : 'OK'])->all(),
            ]);
        }

        return view('finance::stock.index', ['levels' => $levels]);
    }

    public function show(Request $request, StockService $stock, string $item): View
    {
        $tenantId = $this->allow($request, 'finance.view');
        $model = FinItem::query()->where('tenant_id', $tenantId)->where('uuid', $item)->firstOrFail();

        return view('finance::stock.show', [
            'item' => $model,
            'onHand' => $stock->onHand($model),
            'movements' => FinStockMovement::query()->where('item_id', $model->id)->orderByDesc('moved_on')->orderByDesc('id')->limit(100)->get(),
            'kinds' => FinStockMovement::KINDS,
        ]);
    }

    public function store(Request $request, StockService $stock, string $item): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.invoices.manage');
        $model = FinItem::query()->where('tenant_id', $tenantId)->where('uuid', $item)->firstOrFail();
        $data = $request->validate([
            'kind' => ['required', 'in:opening,purchase,adjustment'],
            'quantity' => ['required', 'numeric'],
            'moved_on' => ['required', 'date'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['quantity.required' => 'Enter a quantity.']);

        try {
            $stock->record($model, $data['kind'], (float) $data['quantity'], Carbon::parse($data['moved_on']), isset($data['unit_cost']) ? Money::toMinor($data['unit_cost']) : null, $data['note'] ?? null, $this->actorId($request));
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Stock updated.');
    }
}
