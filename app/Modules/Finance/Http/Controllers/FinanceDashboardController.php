<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\FinInvoice;
use App\Modules\Finance\Services\FinanceReports;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class FinanceDashboardController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request, FinanceReports $reports, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'finance.view');

        return view('finance::dashboard', [
            'figures' => $reports->dashboard($tenantId),
            'currency' => $settings->for($tenantId)->base_currency,
            'recentInvoices' => FinInvoice::query()->where('tenant_id', $tenantId)->with('contact')->latest()->limit(8)->get(),
        ]);
    }
}
