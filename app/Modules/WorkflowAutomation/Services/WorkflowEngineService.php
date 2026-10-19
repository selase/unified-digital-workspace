<?php

declare(strict_types=1);

namespace App\Modules\WorkflowAutomation\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\WorkflowAutomation\Models\WorkflowExecutionLog;
use App\Modules\WorkflowAutomation\Models\WorkflowRule;
use App\Notifications\InstitutionalWorkflowAlert;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class WorkflowEngineService
{
    public const MAX_CHAIN_DEPTH = 3;

    /**
     * Triggers a workflow event for a tenant and executes matching active rules.
     *
     * @param  array<string, mixed>  $payload
     * @return list<WorkflowExecutionLog>
     */
    public function trigger(string $tenantId, string $event, array $payload, int $depth = 1): array
    {
        if ($depth === 1) {
            $tenant = Tenant::query()->find($tenantId);

            if (! $tenant instanceof Tenant) {
                Log::warning("Workflow event [{$event}] ignored: unknown tenant [{$tenantId}].");

                return [];
            }

            app(TenantDatabaseManager::class)->activate($tenant);
        }

        // 1. Recursion Circuit Breaker
        if ($depth > self::MAX_CHAIN_DEPTH) {
            Log::warning("Workflow recursion circuit breaker tripped for tenant [{$tenantId}] on event [{$event}] at depth [{$depth}].");

            $initiatingRuleId = $payload['_initiating_rule_id'] ?? WorkflowRule::where('tenant_id', $tenantId)->value('id');

            if (! $initiatingRuleId) {
                return [];
            }

            $abortedLog = WorkflowExecutionLog::create([
                'tenant_id' => $tenantId,
                'workflow_rule_id' => $initiatingRuleId,
                'trigger_event' => $event,
                'context_payload' => $payload,
                'actions_executed' => [],
                'status' => 'loop_aborted',
                'execution_depth' => $depth,
                'error_message' => "Recursion circuit breaker tripped: execution depth [{$depth}] exceeds maximum allowed depth [".self::MAX_CHAIN_DEPTH.']. Cascading stopped.',
                'executed_at' => now(),
            ]);

            return [$abortedLog];
        }

        $rules = WorkflowRule::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('trigger_event', $event)
            ->get();

        $executionLogs = [];

        foreach ($rules as $rule) {
            if (! $this->evaluateConditions($rule->conditions, $payload)) {
                continue;
            }

            $executedActions = [];
            $status = 'success';
            $errorMessage = null;

            try {
                $executedActions = $this->executeActions($rule, $payload, $depth);
                $rule->increment('execution_count');
            } catch (Throwable $e) {
                $status = 'failed';
                $errorMessage = $e->getMessage();
                Log::error("Workflow rule #{$rule->id} execution failed: ".$e->getMessage());
            }

            $log = WorkflowExecutionLog::create([
                'tenant_id' => $tenantId,
                'workflow_rule_id' => $rule->id,
                'trigger_event' => $event,
                'context_payload' => $payload,
                'actions_executed' => $executedActions,
                'status' => $status,
                'execution_depth' => $depth,
                'error_message' => $errorMessage,
                'executed_at' => now(),
            ]);

            $executionLogs[] = $log;

            // Handle any cascading downstream events from actions
            foreach ($executedActions as $act) {
                if (($act['type'] ?? '') === 'trigger_downstream' && ! empty($act['downstream_event'])) {
                    $downstreamPayload = is_array($act['downstream_payload'] ?? null)
                        ? $act['downstream_payload']
                        : $payload;
                    $downstreamPayload['_initiating_rule_id'] = $rule->id;

                    $cascadeLogs = $this->trigger(
                        $tenantId,
                        (string) $act['downstream_event'],
                        $downstreamPayload,
                        $depth + 1
                    );

                    foreach ($cascadeLogs as $cl) {
                        $executionLogs[] = $cl;
                    }
                }
            }
        }

        return $executionLogs;
    }

    /**
     * Evaluates whether payload satisfies rule conditions.
     *
     * @param  array<int, array{field: string, operator: string, value: mixed}>|null  $conditions
     * @param  array<string, mixed>  $payload
     */
    public function evaluateConditions(?array $conditions, array $payload): bool
    {
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $cond) {
            $field = $cond['field'];
            $op = $cond['operator'];
            $expected = $cond['value'];

            if ($field === '') {
                continue;
            }

            $actual = $payload[$field] ?? null;

            $matches = match ($op) {
                '=', 'equals' => (string) $actual === (string) $expected,
                '!=', 'not_equals' => (string) $actual !== (string) $expected,
                '>' => (float) $actual > (float) $expected,
                '<' => (float) $actual < (float) $expected,
                '>=' => (float) $actual >= (float) $expected,
                '<=' => (float) $actual <= (float) $expected,
                'contains' => is_string($actual) && str_contains(mb_strtolower($actual), mb_strtolower((string) $expected)),
                'in' => is_array($expected) && in_array($actual, $expected, true),
                default => false,
            };

            if (! $matches) {
                return false;
            }
        }

        return true;
    }

    /**
     * Executes the configured actions for a rule.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function executeActions(WorkflowRule $rule, array $payload, int $depth): array
    {
        $executed = [];

        foreach ($rule->actions ?? [] as $action) {
            $type = $action['type'] ?? 'log_audit_alert';

            switch ($type) {
                case 'dispatch_notification':
                case 'send_notification':
                    $title = $this->interpolateTemplate($action['title_template'] ?? $action['title'] ?? 'Workflow Notification: {event}', $payload, $rule);
                    $body = $this->interpolateTemplate($action['body_template'] ?? $action['message'] ?? 'Triggered by workflow rule {rule_name}', $payload, $rule);

                    // Find recipient user if specified or send to creator/admins
                    $targetRole = $action['target_role'] ?? 'Super Admin';
                    $targetUsers = User::where('tenant_id', $rule->tenant_id)
                        ->get()
                        ->filter(fn (User $u): bool => $u->hasRole($targetRole));

                    $notification = new InstitutionalWorkflowAlert(
                        title: $title,
                        message: $body,
                        workflowRuleId: (int) $rule->id,
                        actionUrl: route('workflow-automation.show', $rule),
                    );

                    Notification::send($targetUsers, $notification);

                    $executed[] = [
                        'type' => 'dispatch_notification',
                        'target_role' => $targetRole,
                        'title' => $title,
                        'recipient_count' => $targetUsers->count(),
                    ];
                    break;

                case 'log_audit_alert':
                    $msg = $this->interpolateTemplate($action['message'] ?? 'Alert from workflow rule {rule_name}', $payload, $rule);
                    Log::channel('single')->info("[Institutional Workflow Audit Alert] Tenant [{$rule->tenant_id}] Rule [{$rule->name}]: {$msg}");

                    $executed[] = [
                        'type' => 'log_audit_alert',
                        'level' => $action['alert_level'] ?? 'info',
                        'message' => $msg,
                    ];
                    break;

                case 'trigger_downstream':
                    $downstreamEvent = (string) ($action['downstream_event'] ?? '');
                    if ($downstreamEvent !== '') {
                        $this->trigger(
                            $rule->tenant_id,
                            $downstreamEvent,
                            $action['downstream_payload'] ?? $payload,
                            $depth + 1
                        );
                    }
                    $executed[] = [
                        'type' => 'trigger_downstream',
                        'downstream_event' => $downstreamEvent,
                        'downstream_payload' => $action['downstream_payload'] ?? $payload,
                    ];
                    break;

                default:
                    $executed[] = [
                        'type' => $type,
                        'status' => 'skipped_unknown_action',
                    ];
                    break;
            }
        }

        return $executed;
    }

    /**
     * Helper to replace placeholders like {field} or {rule_name} from payload and rule metadata.
     *
     * @param  array<string, mixed>  $payload
     */
    private function interpolateTemplate(string $template, array $payload, WorkflowRule $rule): string
    {
        /** @var array<string, string> $replacements */
        $replacements = [
            '{rule_name}' => (string) $rule->name,
            '{{rule_name}}' => (string) $rule->name,
            '{rule_id}' => (string) $rule->id,
            '{{rule_id}}' => (string) $rule->id,
            '{event}' => (string) $rule->trigger_event,
            '{{event}}' => (string) $rule->trigger_event,
        ];

        foreach ($payload as $key => $val) {
            if (is_scalar($val)) {
                $replacements['{'.$key.'}'] = (string) $val;
                $replacements['{{'.$key.'}}'] = (string) $val;
            }
        }

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}
