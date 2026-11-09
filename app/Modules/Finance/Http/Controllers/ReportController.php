<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Http\Requests\StatementRangeRequest;
use App\Modules\Finance\Models\FinFund;
use App\Modules\Finance\Services\FinanceReports;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Services\StatementReports;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ReportController extends Controller
{
    use ResolvesFinanceTenant;

    public function agedReceivables(Request $request, FinanceReports $reports, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'finance.view');

        return view('finance::reports.aged-receivables', [
            'report' => $reports->agedReceivables($tenantId),
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function agedPayables(Request $request, FinanceReports $reports, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'finance.view');

        return view('finance::reports.aged-payables', [
            'report' => $reports->agedPayables($tenantId),
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function trialBalance(Request $request, FinanceReports $reports, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'finance.view');
        $asOf = $request->query('as_of');

        return view('finance::reports.trial-balance', [
            'report' => $reports->trialBalance($tenantId, is_string($asOf) && $asOf !== '' ? $asOf : null),
            'asOf' => $asOf,
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function profitAndLoss(StatementRangeRequest $request, StatementReports $statements, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'finance.view');
        [$from, $to] = $this->period($request, $statements, $tenantId);
        $fundId = $request->filled('fund_id') ? (int) $request->validated('fund_id') : null;

        return view('finance::reports.profit-and-loss', [
            'report' => $statements->profitAndLoss($tenantId, $from, $to, $fundId),
            'from' => $from,
            'to' => $to,
            'fundId' => $fundId,
            'funds' => FinFund::query()->where('tenant_id', $tenantId)->orderBy('name')->get(),
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function balanceSheet(StatementRangeRequest $request, StatementReports $statements, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'finance.view');
        $asOf = $request->filled('as_of') ? CarbonImmutable::parse((string) $request->validated('as_of')) : today();

        return view('finance::reports.balance-sheet', [
            'report' => $statements->balanceSheet($tenantId, $asOf),
            'asOf' => $asOf,
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function cashFlow(StatementRangeRequest $request, StatementReports $statements, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'finance.view');
        [$from, $to] = $this->period($request, $statements, $tenantId);

        return view('finance::reports.cash-flow', [
            'report' => $statements->cashFlow($tenantId, $from, $to),
            'from' => $from,
            'to' => $to,
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function funds(StatementRangeRequest $request, StatementReports $statements, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'finance.view');
        [$from, $to] = $this->period($request, $statements, $tenantId);

        return view('finance::reports.funds', [
            'report' => $statements->fundReport($tenantId, $from, $to),
            'from' => $from,
            'to' => $to,
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    /**
     * The requested period, defaulting to the current financial year so far.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function period(StatementRangeRequest $request, StatementReports $statements, string $tenantId): array
    {
        $to = $request->filled('to') ? CarbonImmutable::parse((string) $request->validated('to')) : CarbonImmutable::today();
        $from = $request->filled('from') ? CarbonImmutable::parse((string) $request->validated('from')) : $statements->fiscalYearStart($tenantId, $to);

        return [$from, $to];
    }
}
