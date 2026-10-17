<?php

declare(strict_types=1);

namespace App\Modules\WorkflowAutomation\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\WorkflowAutomation\Models\WorkflowExecutionLog;
use App\Modules\WorkflowAutomation\Models\WorkflowRule;
use App\Modules\WorkflowAutomation\Services\WorkflowEngineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class WorkflowAutomationWebController extends Controller
{
    public function __construct(
        private readonly WorkflowEngineService $engine
    ) {}

    public function index(Request $request): View
    {
        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->id : (string) $request->user()?->tenant_id;

        $rules = WorkflowRule::where('tenant_id', $tenantId)
            ->with(['creator:uuid,first_name,last_name,email'])
            ->latest()
            ->paginate(15);

        $recentLogs = WorkflowExecutionLog::where('tenant_id', $tenantId)
            ->with(['rule'])
            ->latest('executed_at')
            ->limit(10)
            ->get();

        return view('workflow-automation::index', compact('rules', 'recentLogs', 'tenant'));
    }

    public function create(Request $request): View
    {
        $tenant = $request->attributes->get('tenant');

        $availableEvents = [
            'incident.logged' => 'Incident Logged (Safety & CAPA)',
            'leave.submitted' => 'HR Leave Request Submitted',
            'crm.inquiry.created' => 'CRM Lead / Inquiry Received',
            'store.order.paid' => 'Store Order Paid & Confirmed',
            'facilities.request.created' => 'Facility Maintenance Requested',
            'form.submitted' => 'Dynamic Form / Survey Submitted',
        ];

        return view('workflow-automation::create', compact('availableEvents', 'tenant'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->id : (string) $request->user()?->tenant_id;
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'trigger_event' => ['required', 'string'],
            'condition_field' => ['nullable', 'string'],
            'condition_operator' => ['nullable', 'string'],
            'condition_value' => ['nullable', 'string'],
            'action_type' => ['required', 'string'],
            'target_role' => ['nullable', 'string'],
            'title_template' => ['nullable', 'string'],
            'body_template' => ['nullable', 'string'],
        ]);

        $conditions = [];
        if (! empty($validated['condition_field'])) {
            $conditions[] = [
                'field' => $validated['condition_field'],
                'operator' => $validated['condition_operator'] ?? '=',
                'value' => $validated['condition_value'] ?? '',
            ];
        }

        $actions = [];
        if ($validated['action_type'] === 'dispatch_notification') {
            $actions[] = [
                'type' => 'dispatch_notification',
                'target_role' => $validated['target_role'] ?? 'Super Admin',
                'title_template' => $validated['title_template'] ?? 'Workflow Notice: {event}',
                'body_template' => $validated['body_template'] ?? 'Triggered by {rule_name}',
            ];
        } else {
            $actions[] = [
                'type' => 'log_audit_alert',
                'alert_level' => 'warning',
                'message' => $validated['body_template'] ?? 'Automated alert triggered by {rule_name}',
            ];
        }

        $rule = WorkflowRule::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'trigger_event' => $validated['trigger_event'],
            'conditions' => $conditions,
            'actions' => $actions,
            'is_active' => true,
            'created_by_user_id' => $user?->uuid !== null ? (string) $user->uuid : null,
        ]);

        return redirect()->route('workflow-automation.show', $rule)
            ->with('status', 'success')
            ->with('message', "Workflow rule [{$rule->name}] created successfully.");
    }

    public function show(Request $request, WorkflowRule $rule): View
    {
        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->id : (string) $request->user()?->tenant_id;

        if ($rule->tenant_id !== $tenantId) {
            abort(404, 'Workflow rule not found in this organization.');
        }

        $logs = $rule->executionLogs()
            ->latest('executed_at')
            ->paginate(20);

        return view('workflow-automation::show', compact('rule', 'logs', 'tenant'));
    }

    public function toggle(Request $request, WorkflowRule $rule): RedirectResponse
    {
        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->id : (string) $request->user()?->tenant_id;

        if ($rule->tenant_id !== $tenantId) {
            abort(404, 'Workflow rule not found in this organization.');
        }

        $rule->update(['is_active' => ! $rule->is_active]);

        $state = $rule->is_active ? 'activated' : 'deactivated';

        return back()
            ->with('status', 'success')
            ->with('message', "Workflow rule [{$rule->name}] has been {$state}.");
    }

    public function testRun(Request $request, WorkflowRule $rule): RedirectResponse
    {
        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->id : (string) $request->user()?->tenant_id;

        if ($rule->tenant_id !== $tenantId) {
            abort(404, 'Workflow rule not found in this organization.');
        }

        $payload = $request->input('payload');
        if (is_string($payload)) {
            $payload = json_decode($payload, true) ?? [];
        }
        if (! is_array($payload)) {
            $payload = ['simulated' => true, 'timestamp' => now()->toIso8601String()];
        }

        $logs = $this->engine->trigger($tenantId, $rule->trigger_event, $payload);

        return back()
            ->with('status', 'success')
            ->with('message', 'Simulated test execution completed. Evaluated and generated '.count($logs).' execution logs.');
    }

    public function destroy(Request $request, WorkflowRule $rule): RedirectResponse
    {
        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->id : (string) $request->user()?->tenant_id;

        if ($rule->tenant_id !== $tenantId) {
            abort(404, 'Workflow rule not found in this organization.');
        }

        $name = $rule->name;
        $rule->delete();

        return redirect()->route('workflow-automation.index')
            ->with('status', 'success')
            ->with('message', "Workflow rule [{$name}] deleted.");
    }
}
