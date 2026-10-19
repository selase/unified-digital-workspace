<?php

declare(strict_types=1);

namespace App\Modules\WorkflowAutomation\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $description
 * @property string $trigger_event
 * @property array<int, array{field: string, operator: string, value: mixed}>|null $conditions
 * @property array<int, array<string, mixed>>|null $actions
 * @property bool $is_active
 * @property int $execution_count
 * @property string|null $created_by_user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class WorkflowRule extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'trigger_event',
        'conditions',
        'actions',
        'is_active',
        'execution_count',
        'created_by_user_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'uuid');
    }

    /**
     * @return HasMany<WorkflowExecutionLog, $this>
     */
    public function executionLogs(): HasMany
    {
        return $this->hasMany(WorkflowExecutionLog::class, 'workflow_rule_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'is_active' => 'boolean',
            'execution_count' => 'integer',
        ];
    }
}
