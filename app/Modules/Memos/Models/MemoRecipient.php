<?php

declare(strict_types=1);

namespace App\Modules\Memos\Models;

use App\Models\User;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Organization\Directorate;
use App\Modules\HrmsCore\Models\Organization\Unit;
use App\Modules\Memos\Database\Factories\MemoRecipientFactory;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $memo_id
 * @property string $tenant_id
 * @property string $recipient_type
 * @property string|null $recipient_id
 * @property string $role
 * @property bool $requires_ack
 * @property \Illuminate\Support\Carbon|null $acknowledged_at
 * @property string|null $acknowledged_by_id
 * @property string|null $shared_by_id
 * @property \Illuminate\Support\Carbon|null $shared_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @use HasFactory<MemoRecipientFactory>
 */
final class MemoRecipient extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const string TYPE_USER = 'user';

    public const string TYPE_UNIT = 'unit';

    public const string TYPE_DEPARTMENT = 'department';

    public const string TYPE_DIRECTORATE = 'directorate';

    public const string TYPE_TENANT = 'tenant';

    public const string ROLE_TO = 'to';

    public const string ROLE_CC = 'cc';

    protected $table = 'memo_recipients';

    protected $fillable = [
        'memo_id',
        'tenant_id',
        'recipient_type',
        'recipient_id',
        'role',
        'requires_ack',
        'acknowledged_at',
        'acknowledged_by_id',
        'shared_by_id',
        'shared_at',
    ];

    /**
     * @return BelongsTo<Memo, $this>
     */
    public function memo(): BelongsTo
    {
        return $this->belongsTo(Memo::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'recipient_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'recipient_id');
    }

    /**
     * @return BelongsTo<Directorate, $this>
     */
    public function directorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class, 'recipient_id');
    }

    /**
     * @return array{type: string, id: int|string|null, name?: string}|null
     */
    public function recipientSummary(): ?array
    {
        return match ($this->recipient_type) {
            self::TYPE_USER => $this->relationLoaded('user') && $this->user
                ? [
                    'type' => self::TYPE_USER,
                    'id' => $this->user->id,
                    'name' => $this->user->displayName(),
                ]
                : null,
            self::TYPE_UNIT => $this->relationLoaded('unit') && $this->unit
                ? [
                    'type' => self::TYPE_UNIT,
                    'id' => $this->unit->id,
                    'name' => $this->unit->name,
                ]
                : null,
            self::TYPE_DEPARTMENT => $this->relationLoaded('department') && $this->department
                ? [
                    'type' => self::TYPE_DEPARTMENT,
                    'id' => $this->department->id,
                    'name' => $this->department->name,
                ]
                : null,
            self::TYPE_DIRECTORATE => $this->relationLoaded('directorate') && $this->directorate
                ? [
                    'type' => self::TYPE_DIRECTORATE,
                    'id' => $this->directorate->id,
                    'name' => $this->directorate->name,
                ]
                : null,
            self::TYPE_TENANT => [
                'type' => self::TYPE_TENANT,
                'id' => null,
            ],
            default => null,
        };
    }

    /**
     * @return Factory<static>
     */
    protected static function newFactory(): Factory
    {
        return MemoRecipientFactory::new();
    }

    protected function casts(): array
    {
        return [
            'requires_ack' => 'boolean',
            'acknowledged_at' => 'datetime',
            'shared_at' => 'datetime',
        ];
    }
}
