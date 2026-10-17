<?php

declare(strict_types=1);

namespace App\Modules\WorkflowAutomation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $workflow_rule_id
 * @property string $trigger_event
 * @property array<string, mixed>|null $context_payload
 * @property array<int, mixed>|null $actions_executed
 * @property string $status
 * @property int $execution_depth
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $executed_at
 * @property-read WorkflowRule|null $rule
 */
final class WorkflowExecutionLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'workflow_rule_id',
        'trigger_event',
        'context_payload',
        'actions_executed',
        'status',
        'execution_depth',
        'error_message',
        'executed_at',
    ];

    /**
     * @return BelongsTo<WorkflowRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(WorkflowRule::class, 'workflow_rule_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context_payload' => 'array',
            'actions_executed' => 'array',
            'execution_depth' => 'integer',
            'executed_at' => 'datetime',
        ];
    }
}
