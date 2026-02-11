<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TaskAssignment extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'task_id',
        'user_id',
        'assigned_by_id',
        'assigned_at',
    ];

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }
}
