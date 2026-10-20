<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enum\UsageMetric;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveTaxRequest;
use App\Http\Requests\Admin\SaveUsagePricesRequest;
use App\Models\Tax;
use App\Models\UsagePrice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class RateCardController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('access-superadmin-dashboard');

        $currency = $request->query('currency', 'USD');
        abort_unless(in_array($currency, ['USD', 'GHS'], true), 422, 'Choose USD or GHS.');
        $globalPrices = UsagePrice::whereNull('target_type')->where('currency', $currency)->get();
        $metrics = UsageMetric::cases();
        $taxes = Tax::orderBy('priority')->get();

        return view('admin.billing.rate_cards.index', [
            'globalPrices' => $globalPrices,
            'currency' => $currency,
            'metrics' => $metrics,
            'taxes' => $taxes,
            'breadcrumbs' => [
                ['link' => route('dashboard'), 'name' => __('Home')],
                ['name' => 'Rate Cards & Taxes'],
            ],
        ]);
    }

    public function updatePrices(SaveUsagePricesRequest $request)
    {
        $this->authorize('access-superadmin-dashboard');

        $validated = $request->validated();
        $currency = $validated['usage_currency'] ?? 'USD';

        foreach ($validated['usage_prices'] ?? [] as $metricValue => $data) {
            if (($data['unit_price'] ?? null) === null || $data['unit_price'] === '') {
                UsagePrice::whereNull('target_type')->where('metric', $metricValue)->where('currency', $currency)->delete();

                continue;
            }

            UsagePrice::updateOrCreate(
                ['target_type' => null, 'target_id' => null, 'metric' => $metricValue, 'currency' => $currency],
                [
                    'unit_price' => $data['unit_price'] ?? 0,
                    'unit_quantity' => $data['unit_quantity'] ?? 1,
                    'currency' => $currency,
                ]
            );
        }

        return back()->with('success', 'Global unit prices updated successfully');
    }

    public function storeTax(SaveTaxRequest $request): RedirectResponse
    {
        $this->authorize('access-superadmin-dashboard');

        $validated = $request->validated();

        Tax::create([
            'name' => $validated['name'],
            'rate' => $validated['rate'],
            'priority' => $validated['priority'],
            'is_compound' => $request->boolean('is_compound'),
            'country_code' => $validated['country_code'] ?? null,
            'is_active' => $request->boolean('is_active', ($validated['country_code'] ?? null) === null),
        ]);

        return back()->with('success', 'Tax rule created successfully');
    }

    public function updateTax(SaveTaxRequest $request, Tax $tax): RedirectResponse
    {
        $this->authorize('access-superadmin-dashboard');

        $validated = $request->validated();

        $tax->update([
            'name' => $validated['name'],
            'rate' => $validated['rate'],
            'priority' => $validated['priority'],
            'is_compound' => $request->boolean('is_compound'),
            'country_code' => array_key_exists('country_code', $validated) ? $validated['country_code'] : $tax->country_code,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Tax rule updated successfully');
    }

    public function destroyTax(Tax $tax)
    {
        $this->authorize('access-superadmin-dashboard');

        $tax->delete();

        return back()->with('success', 'Tax rule deleted successfully');
    }
}
