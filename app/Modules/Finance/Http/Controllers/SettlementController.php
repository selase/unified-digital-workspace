<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\FinSettlement;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

final class SettlementController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'finance.view');

        return view('finance::settlements.index', [
            'settlements' => FinSettlement::query()->where('tenant_id', $tenantId)->orderByDesc('settled_on')->orderByDesc('id')->paginate(25),
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function import(Request $request, SettlementService $settlements): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.bank.manage');

        try {
            $new = $settlements->import($tenantId);
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        } catch (Throwable $error) {
            report($error);

            return back()->with('error', 'The payment gateway could not be reached. Try again in a moment.');
        }

        return back()->with('success', $new.' new payout(s) found.');
    }

    public function post(Request $request, SettlementService $settlements, string $settlement): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.bank.manage');
        $model = FinSettlement::query()->where('tenant_id', $tenantId)->where('uuid', $settlement)->firstOrFail();

        try {
            $settlements->post($model, null, $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Payout recorded: net to the bank, fees to charges.');
    }
}
