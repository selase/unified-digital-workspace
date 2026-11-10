<?php

declare(strict_types=1);

namespace App\Modules\Grants\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Support\Money;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use App\Modules\Finance\Services\ReportCsv;
use App\Modules\Finance\Services\ReportPdf;
use App\Modules\Grants\Http\Requests\ReceiveInstalmentRequest;
use App\Modules\Grants\Http\Requests\SaveBudgetLineRequest;
use App\Modules\Grants\Http\Requests\SaveGrantRequest;
use App\Modules\Grants\Http\Requests\SaveInstalmentRequest;
use App\Modules\Grants\Http\Requests\SaveReportDeadlineRequest;
use App\Modules\Grants\Models\GrtBudgetLine;
use App\Modules\Grants\Models\GrtFunder;
use App\Modules\Grants\Models\GrtGrant;
use App\Modules\Grants\Models\GrtInstalment;
use App\Modules\Grants\Models\GrtReport;
use App\Modules\Grants\Services\GrantService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class GrantController extends Controller
{
    use ResolvesFinanceTenant;

    public function overview(Request $request, GrantService $grants, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'grants.view');
        $all = GrtGrant::query()->where('tenant_id', $tenantId)->with(['funder', 'instalments'])->get();
        $committed = $all->filter(fn (GrtGrant $grant): bool => $grant->isCommitted() && $grant->status !== 'closed');

        return view('grants::dashboard', [
            'rows' => $committed->map(fn (GrtGrant $grant): array => ['grant' => $grant, 'position' => $grants->position($grant)])->values(),
            'pipelineTotal' => (int) $all->where('status', 'pipeline')->sum('total_amount'),
            'upcoming' => GrtInstalment::query()->where('tenant_id', $tenantId)->where('status', 'expected')->with('grant.funder')->orderBy('expected_on')->limit(6)->get(),
            'reports' => GrtReport::query()->where('tenant_id', $tenantId)->where('status', 'pending')->with('grant.funder')->orderBy('due_on')->limit(6)->get(),
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function index(Request $request, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'grants.view');

        return view('grants::grants.index', [
            'grants' => GrtGrant::query()->where('tenant_id', $tenantId)->with('funder')->orderByRaw("case status when 'active' then 0 when 'awarded' then 1 when 'pipeline' then 2 else 3 end")->orderByDesc('starts_on')->get(),
            'funders' => GrtFunder::query()->where('tenant_id', $tenantId)->orderBy('name')->get(),
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function store(SaveGrantRequest $request, GrantService $grants): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $funder = GrtFunder::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->validated('funder_id'));

        try {
            $grant = $grants->create($tenantId, $funder, Money::toMinor($request->validated('total_amount')), $request->safe()->only(['title', 'reference', 'starts_on', 'ends_on', 'status', 'purpose', 'notes']));
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('grants.grants.show', $grant->uuid)->with('success', 'Grant saved. A restricted fund was created for it.');
    }

    public function show(Request $request, GrantService $grants, FinanceSettings $settings, string $grant): View
    {
        $tenantId = $this->allow($request, 'grants.view');
        $model = $this->find($tenantId, $grant)->load(['funder', 'fund', 'instalments', 'reports']);

        return view('grants::grants.show', [
            'grant' => $model,
            'position' => $grants->position($model),
            'plan' => $grants->budgetVsActual($model),
            'expenseAccounts' => FinAccount::query()->where('tenant_id', $tenantId)->where('type', 'expense')->where('is_active', true)->orderBy('code')->get(),
            'depositAccounts' => FinAccount::query()->where('tenant_id', $tenantId)->where('type', 'asset')->where('is_active', true)->orderBy('code')->get(),
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function status(Request $request, string $grant): RedirectResponse
    {
        $tenantId = $this->allow($request, 'grants.manage');
        $status = (string) $request->input('status');
        abort_unless(array_key_exists($status, GrtGrant::STATUSES), 422);
        $this->find($tenantId, $grant)->update(['status' => $status]);

        return back()->with('success', 'Grant status updated.');
    }

    public function addInstalment(SaveInstalmentRequest $request, GrantService $grants, string $grant): RedirectResponse
    {
        try {
            $grants->addInstalment($this->find($this->tenantId($request), $grant), Money::toMinor($request->validated('amount')), Carbon::parse($request->validated('expected_on')), $this->actorId($request));
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Instalment added.');
    }

    public function receiveInstalment(ReceiveInstalmentRequest $request, GrantService $grants, string $instalment): RedirectResponse
    {
        $model = GrtInstalment::query()->where('tenant_id', $this->tenantId($request))->where('uuid', $instalment)->firstOrFail();

        try {
            $grants->receive($model, Carbon::parse($request->validated('date')), $request->validated('deposit_account_id') ? (int) $request->validated('deposit_account_id') : null, $request->validated('reference'), $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Instalment received and posted to the books.');
    }

    public function voidInstalment(Request $request, GrantService $grants, string $instalment): RedirectResponse
    {
        $tenantId = $this->allow($request, 'grants.record');
        $model = GrtInstalment::query()->where('tenant_id', $tenantId)->where('uuid', $instalment)->firstOrFail();

        try {
            $grants->void($model, $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Instalment voided and its entry reversed.');
    }

    public function saveBudgetLine(SaveBudgetLineRequest $request, GrantService $grants, string $grant): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $account = FinAccount::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->validated('account_id'));

        try {
            $grants->setBudgetLine($this->find($tenantId, $grant), $account, Money::toMinor($request->validated('amount')));
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Budget line saved.');
    }

    public function removeBudgetLine(Request $request, GrantService $grants, string $grant, int $account): RedirectResponse
    {
        $tenantId = $this->allow($request, 'grants.manage');
        $line = GrtBudgetLine::query()->where('tenant_id', $tenantId)->where('grant_id', $this->find($tenantId, $grant)->id)->where('account_id', $account)->firstOrFail();
        $grants->removeBudgetLine($line);

        return back()->with('success', 'Budget line removed.');
    }

    /** Downloads the funder report as CSV (default) or PDF. */
    public function funderReport(Request $request, GrantService $grants, ReportCsv $csv, ReportPdf $pdf, string $grant): StreamedResponse|\Illuminate\Http\Response
    {
        $tenantId = $this->allow($request, 'grants.view');
        $model = $this->find($tenantId, $grant);
        $rows = $grants->funderReport($model);
        $name = 'funder-report-'.Str::slug($model->title);

        return $request->query('format') === 'pdf' ? $pdf->download($tenantId, $name, $rows) : $csv->download($name.'.csv', $rows);
    }

    public function addReport(SaveReportDeadlineRequest $request, string $grant): RedirectResponse
    {
        $model = $this->find($this->tenantId($request), $grant);
        GrtReport::query()->create([...$request->validated(), 'tenant_id' => $model->tenant_id, 'grant_id' => $model->id, 'status' => 'pending']);

        return back()->with('success', 'Reporting deadline added.');
    }

    public function submitReport(Request $request, string $report): RedirectResponse
    {
        $tenantId = $this->allow($request, 'grants.manage');
        GrtReport::query()->where('tenant_id', $tenantId)->where('uuid', $report)->firstOrFail()->update(['status' => 'submitted', 'submitted_on' => today()]);

        return back()->with('success', 'Report marked as submitted.');
    }

    private function find(string $tenantId, string $uuid): GrtGrant
    {
        return GrtGrant::query()->where('tenant_id', $tenantId)->where('uuid', $uuid)->firstOrFail();
    }
}
