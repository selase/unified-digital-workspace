<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Http\Requests\SaveExpenseRequest;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinAttachment;
use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Models\FinExpense;
use App\Modules\Finance\Models\FinFund;
use App\Modules\Finance\Models\FinTax;
use App\Modules\Finance\Services\ExpenseService;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Support\Money;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ExpenseController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'finance.view');

        return view('finance::expenses.index', [
            'expenses' => FinExpense::query()->where('tenant_id', $tenantId)->with(['account', 'paidFrom', 'contact'])->latest('date')->latest('id')->paginate(25),
            'currency' => $settings->for($tenantId)->base_currency,
            'files' => FinAttachment::query()->where('tenant_id', $tenantId)->where('attachable_type', 'expense')->get()->groupBy('attachable_id'),
            'expenseAccounts' => FinAccount::query()->where('tenant_id', $tenantId)->where('type', 'expense')->where('is_active', true)->orderBy('code')->get(),
            'paidFromAccounts' => FinAccount::query()->where('tenant_id', $tenantId)->where('type', 'asset')->where('is_active', true)->orderBy('code')->get(),
            'vendors' => FinContact::query()->where('tenant_id', $tenantId)->where('is_vendor', true)->where('is_active', true)->orderBy('name')->get(),
            'funds' => FinFund::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(),
            'taxes' => $settings->for($tenantId)->tax_enabled ? FinTax::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get() : collect(),
        ]);
    }

    public function store(SaveExpenseRequest $request, ExpenseService $expenses): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $vendor = $request->validated('contact_id')
            ? FinContact::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->validated('contact_id'))
            : null;

        try {
            $expenses->record(
                $tenantId,
                Money::toMinor($request->validated('amount')),
                Carbon::parse($request->validated('date')),
                (int) $request->validated('account_id'),
                (int) $request->validated('paid_from_account_id'),
                $vendor,
                [
                    'description' => $request->validated('description'),
                    'reference' => $request->validated('reference'),
                    'fund_id' => $request->validated('fund_id') ? (int) $request->validated('fund_id') : null,
                    'tax_id' => $request->validated('tax_id') ? (int) $request->validated('tax_id') : null,
                ],
                $this->actorId($request),
            );
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('finance.expenses.index')->with('success', 'Expense recorded.');
    }

    public function void(Request $request, ExpenseService $expenses, string $expense): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.expenses.manage');
        $model = FinExpense::query()->where('tenant_id', $tenantId)->where('uuid', $expense)->firstOrFail();

        try {
            $expenses->void($model, $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Expense voided and its journal reversed.');
    }
}
