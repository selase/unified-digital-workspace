<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Services\FinanceReports;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
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
}
