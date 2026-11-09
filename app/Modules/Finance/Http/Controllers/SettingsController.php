<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TenantPaymentGateway;
use App\Modules\Finance\Http\Requests\SaveSettingsRequest;
use App\Modules\Finance\Models\FinFund;
use App\Modules\Finance\Models\FinJournal;
use App\Modules\Finance\Models\FinTax;
use App\Modules\Finance\Services\ChartOfAccounts;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Services\YearEndClose;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class SettingsController extends Controller
{
    use ResolvesFinanceTenant;

    public function edit(Request $request, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'finance.settings.manage');

        return view('finance::settings.edit', [
            'settings' => $settings->for($tenantId),
            'funds' => FinFund::query()->where('tenant_id', $tenantId)->orderBy('name')->get(),
            'taxes' => FinTax::query()->where('tenant_id', $tenantId)->get(),
            'closePreview' => app(YearEndClose::class)->preview($tenantId, app(YearEndClose::class)->suggestedDate($tenantId)),
            'hasPostings' => FinJournal::query()->where('tenant_id', $tenantId)->exists(),
            'paystackReady' => TenantPaymentGateway::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->where('provider', 'paystack')->where('is_active', true)->exists(),
            'webhookUrl' => route('finance.webhooks.paystack', ['tenant' => $tenantId]),
        ]);
    }

    public function update(SaveSettingsRequest $request, FinanceSettings $settings): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $model = $settings->for($tenantId);
        $data = $request->safe()->except(['tax_enabled', 'base_currency']);

        if (! FinJournal::query()->where('tenant_id', $tenantId)->exists()) {
            $data['base_currency'] = mb_strtoupper((string) $request->validated('base_currency'));
        }

        $model->update([...$data, 'tax_enabled' => $request->boolean('tax_enabled')]);

        return back()->with('success', 'Settings saved.');
    }

    public function storeFund(Request $request): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.settings.manage');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('fin_funds', 'code')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:255'],
            'is_restricted' => ['sometimes', 'boolean'],
        ], ['code.unique' => 'That fund code is already used.']);

        FinFund::query()->create([...$data, 'tenant_id' => $tenantId, 'is_restricted' => $request->boolean('is_restricted')]);

        return back()->with('success', 'Fund added.');
    }

    public function closeYear(Request $request, YearEndClose $close): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.settings.manage');
        $request->validate(['year_end' => ['required', 'date', 'before_or_equal:today']], ['year_end.required' => 'Choose the last day of the financial year.']);
        abort_unless($request->input('confirm') === 'CLOSE', 422, 'Type CLOSE to confirm.');

        try {
            $journal = $close->close($tenantId, \Carbon\CarbonImmutable::parse((string) $request->input('year_end')), $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Year closed with journal '.$journal->number.'. The books are locked up to '.$journal->date->toDateString().'.');
    }

    public function addNgoAccounts(Request $request, ChartOfAccounts $accounts): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.settings.manage');
        $created = $accounts->addNgoAccounts($tenantId);

        return back()->with('success', $created > 0 ? $created.' foundation accounts added to the chart of accounts.' : 'The foundation accounts were already in the chart of accounts.');
    }

    public function addGhanaTax(Request $request, ChartOfAccounts $accounts): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.settings.manage');
        $accounts->seedGhanaTax($tenantId);

        return back()->with('success', 'Ghana tax preset added. Switch it on below once you are VAT-registered.');
    }

    public function toggleTax(Request $request, string $tax): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.settings.manage');
        $model = FinTax::query()->where('tenant_id', $tenantId)->where('uuid', $tax)->firstOrFail();
        $model->update(['is_active' => ! $model->is_active]);

        return back()->with('success', $model->is_active ? 'Tax rate switched on.' : 'Tax rate switched off.');
    }
}
