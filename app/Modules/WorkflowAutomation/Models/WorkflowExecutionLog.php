<?php

declare(strict_types=1);

namespace App\Modules\WorkflowAutomation\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WorkflowExecutionLog extends Model
{
    use HasFactory;

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
