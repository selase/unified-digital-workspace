<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SaveAlertRuleRequest;
use App\Models\AlertRule;
use App\Services\Alerts\AlertDispatcher;
use App\Services\Alerts\AlertEvent;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AlertSettingsController extends Controller
{
    public function index(TenantContext $tenantContext, AlertDispatcher $alerts): View
    {
        $tenant = $tenantContext->getTenant();

        return view('tenant.alerts.index', [
            'events' => AlertEvent::all(),
            'enabledChannels' => $alerts->enabledChannels($tenant),
            'rules' => AlertRule::query()->where('tenant_id', $tenant->id)->latest()->get(),
            'breadcrumbs' => [['name' => 'Settings', 'link' => route('tenant.settings.index')], ['name' => 'Alerts']],
        ]);
    }

    public function updateChannels(Request $request, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->getTenant();
        $meta = $tenant->meta ?? [];
        $meta['alert_channels'] = [
            'mail' => $request->boolean('mail'),
            'database' => $request->boolean('database'),
            'sms' => $request->boolean('sms'),
        ];
        $tenant->update(['meta' => $meta]);

        return back()->with('success', 'Alert channels updated.');
    }

    public function store(SaveAlertRuleRequest $request, TenantContext $tenantContext): RedirectResponse
    {
        $validated = $request->validated();

        AlertRule::create([
            'tenant_id' => $tenantContext->getTenant()->id,
            'event' => $validated['event'],
            'name' => $validated['name'],
            'channels' => array_values($validated['channels']),
            'recipients' => ['emails' => $validated['emails'] ?? [], 'phones' => $validated['phones'] ?? []],
        ]);

        return back()->with('success', 'Alert rule created.');
    }

    public function toggle(string $subdomain, string $rule, TenantContext $tenantContext): RedirectResponse
    {
        $rule = $this->ownedRule($rule, $tenantContext);
        $rule->update(['is_active' => ! $rule->is_active]);

        return back()->with('success', $rule->is_active ? 'Alert switched on.' : 'Alert switched off.');
    }

    public function destroy(string $subdomain, string $rule, TenantContext $tenantContext): RedirectResponse
    {
        $this->ownedRule($rule, $tenantContext)->delete();

        return back()->with('success', 'Alert rule deleted.');
    }

    private function ownedRule(string $id, TenantContext $tenantContext): AlertRule
    {
        return AlertRule::query()->where('tenant_id', $tenantContext->getTenant()->id)->findOrFail($id);
    }
}
