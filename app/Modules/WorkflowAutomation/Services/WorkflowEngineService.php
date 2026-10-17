<?php

declare(strict_types=1);

namespace App\Modules\WorkflowAutomation\Services;

use App\Models\User;
use App\Modules\WorkflowAutomation\Models\WorkflowExecutionLog;
use App\Modules\WorkflowAutomation\Models\WorkflowRule;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
        // 1. Recursion Circuit Breaker
        if ($depth > self::MAX_CHAIN_DEPTH) {
            Log::warning("Workflow recursion circuit breaker tripped for tenant [{$tenantId}] on event [{$event}] at depth [{$depth}].");

            $abortedLog = WorkflowExecutionLog::create([
                'tenant_id' => $tenantId,
                'workflow_rule_id' => $payload['_initiating_rule_id'] ?? 0,
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
            $field = $cond['field'] ?? null;
            $op = $cond['operator'] ?? '=';
            $expected = $cond['value'] ?? null;

            if ($field === null) {
                continue;
            }

            $actual = $payload[$field] ?? null;

            $matches = match ($op) {
                '=' => (string) $actual === (string) $expected,
                '!=' => (string) $actual !== (string) $expected,
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
                    $title = $this->interpolateTemplate($action['title_template'] ?? 'Workflow Notification: {event}', $payload, $rule);
                    $body = $this->interpolateTemplate($action['body_template'] ?? 'Triggered by workflow rule {rule_name}', $payload, $rule);

                    // Find recipient user if specified or send to creator/admins
                    $targetRole = $action['target_role'] ?? 'Super Admin';
                    $targetUsers = User::where('tenant_id', $rule->tenant_id)
                        ->get()
                        ->filter(fn (User $u): bool => $u->hasRole($targetRole));

                    foreach ($targetUsers as $targetUser) {
                        DatabaseNotification::create([
                            'id' => (string) Str::uuid(),
                            'type' => 'App\Notifications\InstitutionalWorkflowAlert',
                            'notifiable_type' => User::class,
                            'notifiable_id' => $targetUser->id,
                            'data' => [
                                'title' => $title,
                                'message' => $body,
                                'workflow_rule_id' => $rule->id,
                                'action_url' => route('workflow-automation.show', $rule),
                            ],
                            'created_at' => now(),
                        ]);
                    }

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
        $replacements = [
            '{rule_name}' => $rule->name,
            '{rule_id}' => (string) $rule->id,
            '{event}' => $rule->trigger_event,
        ];

        foreach ($payload as $key => $val) {
            if (is_scalar($val)) {
                $replacements['{'.$key.'}'] = (string) $val;
            }
        }

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}
