<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TimeEntry extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $connection = 'landlord';

    protected $fillable = [
        'task_id',
        'user_id',
        'entry_date',
        'minutes',
        'note',
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

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'minutes' => 'integer',
        ];
    }
}
