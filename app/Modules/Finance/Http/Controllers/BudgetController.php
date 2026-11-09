<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Http\Requests\SaveBudgetRequest;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinBudget;
use App\Modules\Finance\Models\FinFund;
use App\Modules\Finance\Services\BudgetReports;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Services\StatementReports;
use App\Modules\Finance\Support\Money;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class BudgetController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request, StatementReports $statements): View
    {
        $tenantId = $this->allow($request, 'finance.view');
        $yearStart = $statements->fiscalYearStart($tenantId, today());

        return view('finance::budgets.index', [
            'budgets' => FinBudget::query()->where('tenant_id', $tenantId)->withCount('lines')->latest('starts_on')->get(),
            'accounts' => FinAccount::query()->where('tenant_id', $tenantId)->whereIn('type', ['income', 'expense'])->where('is_active', true)->orderBy('code')->get(),
            'funds' => FinFund::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(),
            'defaultStart' => $yearStart->toDateString(),
            'defaultEnd' => $yearStart->addYear()->subDay()->toDateString(),
        ]);
    }

    public function store(SaveBudgetRequest $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        $budget = DB::connection('tenant')->transaction(function () use ($request, $tenantId): FinBudget {
            $budget = FinBudget::query()->create([...$request->safe()->only(['name', 'starts_on', 'ends_on', 'notes']), 'tenant_id' => $tenantId]);
            foreach ((array) $request->validated('lines') as $line) {
                $budget->lines()->create([
                    'account_id' => (int) $line['account_id'],
                    'fund_id' => ! empty($line['fund_id']) ? (int) $line['fund_id'] : null,
                    'amount' => Money::toMinor($line['amount']),
                ]);
            }

            return $budget;
        });

        return redirect()->route('finance.budgets.show', $budget->uuid)->with('success', 'Budget saved.');
    }

    public function show(Request $request, BudgetReports $reports, FinanceSettings $settings, string $budget): View
    {
        $tenantId = $this->allow($request, 'finance.view');
        $model = FinBudget::query()->where('tenant_id', $tenantId)->where('uuid', $budget)->firstOrFail();
        $asOf = $request->filled('as_of') ? CarbonImmutable::parse((string) $request->query('as_of')) : CarbonImmutable::today();

        return view('finance::budgets.show', [
            'budget' => $model,
            'report' => $reports->compare($model, $asOf),
            'asOf' => $asOf,
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function destroy(Request $request, string $budget): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.budgets.manage');
        FinBudget::query()->where('tenant_id', $tenantId)->where('uuid', $budget)->firstOrFail()->delete();

        return redirect()->route('finance.budgets.index')->with('success', 'Budget deleted.');
    }
}
