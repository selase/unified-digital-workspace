<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Services\TimeBillingService;
use App\Modules\Finance\Support\Money;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use App\Modules\ProjectManagement\Models\Project;
use App\Services\ModuleManager;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Bill the hours logged on a project. Only offered when the organisation also uses Project Management. */
final class TimeBillingController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request, TimeBillingService $billing, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'finance.view');
        $this->requireProjects($tenantId);

        $to = CarbonImmutable::parse($request->query('to', today()->toDateString()));
        $from = CarbonImmutable::parse($request->query('from', today()->startOfMonth()->toDateString()));
        $project = $request->query('project') ? Project::query()->where('tenant_id', $tenantId)->where('uuid', (string) $request->query('project'))->first() : null;

        return view('finance::time-billing.index', [
            'projects' => Project::query()->where('tenant_id', $tenantId)->orderBy('name')->get(),
            'contacts' => FinContact::query()->where('tenant_id', $tenantId)->where('is_active', true)->where('is_customer', true)->orderBy('name')->get(),
            'incomeAccounts' => FinAccount::query()->where('tenant_id', $tenantId)->where('type', 'income')->where('is_active', true)->orderBy('code')->get(),
            'project' => $project,
            'rows' => $project ? $billing->unbilled($tenantId, $project, $from, $to) : collect(),
            'from' => $from,
            'to' => $to,
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function store(Request $request, TimeBillingService $billing): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.invoices.manage');
        $this->requireProjects($tenantId);
        $data = $request->validate([
            'project' => ['required', 'string'],
            'contact_id' => ['required', 'integer'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'hourly_rate' => ['required', 'numeric', 'gt:0'],
            'account_id' => ['nullable', 'integer'],
        ], ['contact_id.required' => 'Choose the customer to invoice.', 'hourly_rate.gt' => 'Enter an hourly rate greater than zero.', 'to.after_or_equal' => 'The end date must be on or after the start date.']);

        $project = Project::query()->where('tenant_id', $tenantId)->where('uuid', $data['project'])->firstOrFail();
        $contact = FinContact::query()->where('tenant_id', $tenantId)->findOrFail((int) $data['contact_id']);
        $account = ! empty($data['account_id']) ? FinAccount::query()->where('tenant_id', $tenantId)->where('type', 'income')->findOrFail((int) $data['account_id']) : null;

        try {
            $invoice = $billing->createDraftInvoice($tenantId, $contact, $project, CarbonImmutable::parse($data['from']), CarbonImmutable::parse($data['to']), Money::toMinor($data['hourly_rate']), $account?->id, $this->actorId($request));
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('finance.invoices.edit', $invoice->uuid)->with('success', 'Draft invoice created from logged time. Review it, then send it.');
    }

    private function requireProjects(string $tenantId): void
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        abort_unless(app(ModuleManager::class)->isEnabledForTenant('project-management', $tenant), 404);
    }
}
