<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Http\Requests\StatementRangeRequest;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinFund;
use App\Modules\Finance\Services\FinanceReports;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Services\ReportCsv;
use App\Modules\Finance\Services\StatementReports;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportController extends Controller
{
    use ResolvesFinanceTenant;

    public function agedReceivables(Request $request, FinanceReports $reports, FinanceSettings $settings, ReportCsv $csv): View|StreamedResponse
    {
        $tenantId = $this->allow($request, 'finance.view');
        if ($request->query('format') === 'csv') {
            return $csv->download('aged-receivables.csv', $csv->aged($reports->agedReceivables($tenantId), 'receivables', $settings->for($tenantId)->base_currency));
        }

        return view('finance::reports.aged-receivables', [
            'report' => $reports->agedReceivables($tenantId),
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function agedPayables(Request $request, FinanceReports $reports, FinanceSettings $settings, ReportCsv $csv): View|StreamedResponse
    {
        $tenantId = $this->allow($request, 'finance.view');
        if ($request->query('format') === 'csv') {
            return $csv->download('aged-payables.csv', $csv->aged($reports->agedPayables($tenantId), 'payables', $settings->for($tenantId)->base_currency));
        }

        return view('finance::reports.aged-payables', [
            'report' => $reports->agedPayables($tenantId),
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function trialBalance(Request $request, FinanceReports $reports, FinanceSettings $settings, ReportCsv $csv): View|StreamedResponse
    {
        $tenantId = $this->allow($request, 'finance.view');
        $asOf = $request->query('as_of');
        if ($request->query('format') === 'csv') {
            return $csv->download('trial-balance.csv', $csv->trialBalance($reports->trialBalance($tenantId, is_string($asOf) && $asOf !== '' ? $asOf : null), is_string($asOf) && $asOf !== '' ? $asOf : null, $settings->for($tenantId)->base_currency));
        }

        return view('finance::reports.trial-balance', [
            'report' => $reports->trialBalance($tenantId, is_string($asOf) && $asOf !== '' ? $asOf : null),
            'asOf' => $asOf,
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function profitAndLoss(StatementRangeRequest $request, StatementReports $statements, FinanceSettings $settings, ReportCsv $csv): View|StreamedResponse
    {
        $tenantId = $this->allow($request, 'finance.view');
        [$from, $to] = $this->period($request, $statements, $tenantId);
        $fundId = $request->filled('fund_id') ? (int) $request->validated('fund_id') : null;
        if ($request->query('format') === 'csv') {
            return $csv->download('profit-and-loss.csv', $csv->profitAndLoss($statements->profitAndLoss($tenantId, $from, $to, $fundId), $from, $to, $settings->for($tenantId)->base_currency));
        }

        return view('finance::reports.profit-and-loss', [
            'report' => $statements->profitAndLoss($tenantId, $from, $to, $fundId),
            'from' => $from,
            'to' => $to,
            'fundId' => $fundId,
            'funds' => FinFund::query()->where('tenant_id', $tenantId)->orderBy('name')->get(),
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function balanceSheet(StatementRangeRequest $request, StatementReports $statements, FinanceSettings $settings, ReportCsv $csv): View|StreamedResponse
    {
        $tenantId = $this->allow($request, 'finance.view');
        $asOf = $request->filled('as_of') ? CarbonImmutable::parse((string) $request->validated('as_of')) : today();
        if ($request->query('format') === 'csv') {
            return $csv->download('balance-sheet.csv', $csv->balanceSheet($statements->balanceSheet($tenantId, $asOf), $asOf, $settings->for($tenantId)->base_currency));
        }

        return view('finance::reports.balance-sheet', [
            'report' => $statements->balanceSheet($tenantId, $asOf),
            'asOf' => $asOf,
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function cashFlow(StatementRangeRequest $request, StatementReports $statements, FinanceSettings $settings, ReportCsv $csv): View|StreamedResponse
    {
        $tenantId = $this->allow($request, 'finance.view');
        [$from, $to] = $this->period($request, $statements, $tenantId);
        if ($request->query('format') === 'csv') {
            return $csv->download('cash-flow.csv', $csv->cashFlow($statements->cashFlow($tenantId, $from, $to), $from, $to, $settings->for($tenantId)->base_currency));
        }

        return view('finance::reports.cash-flow', [
            'report' => $statements->cashFlow($tenantId, $from, $to),
            'from' => $from,
            'to' => $to,
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function funds(StatementRangeRequest $request, StatementReports $statements, FinanceSettings $settings, ReportCsv $csv): View|StreamedResponse
    {
        $tenantId = $this->allow($request, 'finance.view');
        [$from, $to] = $this->period($request, $statements, $tenantId);
        if ($request->query('format') === 'csv') {
            return $csv->download('fund-report.csv', $csv->funds($statements->fundReport($tenantId, $from, $to), $from, $to, $settings->for($tenantId)->base_currency));
        }

        return view('finance::reports.funds', [
            'report' => $statements->fundReport($tenantId, $from, $to),
            'from' => $from,
            'to' => $to,
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function generalLedger(StatementRangeRequest $request, StatementReports $statements, FinanceSettings $settings, ReportCsv $csv): View|StreamedResponse
    {
        $tenantId = $this->allow($request, 'finance.view');
        [$from, $to] = $this->period($request, $statements, $tenantId);
        $accounts = FinAccount::query()->where('tenant_id', $tenantId)->orderBy('code')->get();
        $account = $request->filled('account_id') ? $accounts->firstWhere('id', (int) $request->query('account_id')) : null;
        $currency = $settings->for($tenantId)->base_currency;
        $report = $account instanceof FinAccount ? $statements->generalLedger($tenantId, $account, $from, $to) : null;

        if ($report !== null && $request->query('format') === 'csv') {
            return $csv->download('general-ledger-'.$account->code.'.csv', $csv->generalLedger($report, $currency));
        }

        return view('finance::reports.general-ledger', ['report' => $report, 'accounts' => $accounts, 'accountId' => $account?->id, 'from' => $from, 'to' => $to, 'currency' => $currency]);
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
