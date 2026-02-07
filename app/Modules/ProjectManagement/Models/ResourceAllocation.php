<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ResourceAllocation extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $connection = 'landlord';

    protected $fillable = [
        'project_id',
        'user_id',
        'start_date',
        'end_date',
        'allocation_percent',
        'role',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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
            'start_date' => 'date',
            'end_date' => 'date',
            'allocation_percent' => 'integer',
        ];
    }
}
