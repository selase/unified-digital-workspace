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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

    public const string ROLE_BCC = 'bcc';

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
     * Load the user / unit / department / directorate each recipient points at.
     *
     * `recipient_id` is a text column that holds a user's uuid, a user's numeric id (the compose
     * form) or the numeric id of an org node. Eager-loading the belongsTo relations compares every
     * recipient_id against one key column, which PostgreSQL rejects for the other formats, so each
     * target is resolved with ids of its own type only.
     *
     * @param  Collection<int, MemoRecipient>  $recipients
     */
    public static function hydrateTargets(Collection $recipients): void
    {
        $refs = fn (string $type): Collection => $recipients
            ->where('recipient_type', $type)
            ->pluck('recipient_id')
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->map(fn ($value): string => (string) $value)
            ->unique()
            ->values();

        $userRefs = $refs(self::TYPE_USER);
        $uuids = $userRefs->filter(fn (string $value): bool => Str::isUuid($value))->values()->all();
        $ids = $userRefs->filter(fn (string $value): bool => ctype_digit($value))->values()->all();

        $users = $uuids === [] && $ids === []
            ? collect()
            : User::query()
                ->select(['id', 'uuid', 'first_name', 'last_name', 'email'])
                ->where(function ($query) use ($uuids, $ids): void {
                    $query->whereIn('uuid', $uuids)->orWhereIn('id', $ids);
                })
                ->get();

        $orgNodes = [
            self::TYPE_UNIT => ['relation' => 'unit', 'model' => Unit::class],
            self::TYPE_DEPARTMENT => ['relation' => 'department', 'model' => Department::class],
            self::TYPE_DIRECTORATE => ['relation' => 'directorate', 'model' => Directorate::class],
        ];

        $nodes = [];
        foreach ($orgNodes as $type => $config) {
            $numericIds = $refs($type)->filter(fn (string $value): bool => ctype_digit($value))->values()->all();
            $model = new $config['model'];

            // The HR tables only exist for tenants that have the HRMS module set up.
            $nodes[$type] = $numericIds === [] || ! Schema::connection($model->getConnectionName())->hasTable($model->getTable())
                ? collect()
                : $config['model']::query()->select(['id', 'name'])->whereIn('id', $numericIds)->get()->keyBy('id');
        }

        foreach ($recipients as $recipient) {
            $ref = (string) $recipient->recipient_id;

            $recipient->setRelation('user', $recipient->recipient_type === self::TYPE_USER
                ? $users->first(fn (User $user): bool => (string) $user->uuid === $ref || (string) $user->id === $ref)
                : null);

            foreach ($orgNodes as $type => $config) {
                $recipient->setRelation(
                    $config['relation'],
                    $recipient->recipient_type === $type ? ($nodes[$type]->get((int) $ref)) : null
                );
            }
        }
    }

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
     * @return BelongsTo<User, $this>
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_id', 'uuid');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by_id', 'uuid');
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
