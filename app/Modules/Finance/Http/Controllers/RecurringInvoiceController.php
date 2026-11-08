<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Http\Requests\SaveRecurringRequest;
use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Models\FinRecurringInvoice;
use App\Modules\Finance\Models\FinTax;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Support\Money;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class RecurringInvoiceController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request): View
    {
        $tenantId = $this->allow($request, 'finance.view');
        $settings = app(FinanceSettings::class)->for($tenantId);

        return view('finance::recurring.index', [
            'templates' => FinRecurringInvoice::query()->where('tenant_id', $tenantId)->with('contact')->orderBy('next_run_date')->get(),
            'contacts' => FinContact::query()->where('tenant_id', $tenantId)->where('is_active', true)->where('is_customer', true)->orderBy('name')->get(),
            'taxes' => $settings->tax_enabled ? FinTax::query()->where('tenant_id', $tenantId)->where('is_active', true)->get() : collect(),
            'frequencies' => FinRecurringInvoice::FREQUENCIES,
            'settings' => $settings,
        ]);
    }

    public function store(SaveRecurringRequest $request): RedirectResponse
    {
        $lines = collect($request->validated('lines'))->map(fn (array $line): array => [
            'description' => $line['description'],
            'quantity' => $line['quantity'],
            'unit_price' => Money::toMinor($line['unit_price']),
            'tax_id' => $line['tax_id'] ?? null,
        ])->values()->all();

        FinRecurringInvoice::query()->create([
            ...$request->safe()->except(['lines', 'auto_send']),
            'tenant_id' => $this->tenantId($request),
            'auto_send' => $request->boolean('auto_send'),
            'lines' => $lines,
        ]);

        return back()->with('success', 'Recurring invoice scheduled.');
    }

    public function toggle(Request $request, string $recurring): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.invoices.manage');
        $model = FinRecurringInvoice::query()->where('tenant_id', $tenantId)->where('uuid', $recurring)->firstOrFail();
        $model->update(['is_active' => ! $model->is_active]);

        return back()->with('success', $model->is_active ? 'Schedule resumed.' : 'Schedule paused.');
    }
}
