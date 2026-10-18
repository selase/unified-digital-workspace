<?php

declare(strict_types=1);

namespace App\Modules\Intranet\Models;

use App\Models\User;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string $audience
 * @property int|null $department_id
 * @property bool $is_pinned
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Department|null $department
 * @property-read \Illuminate\Database\Eloquent\Collection<int, IntranetBoardPost> $posts
 * @property-read int|null $posts_count
 */
final class IntranetMessageBoard extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const string AUDIENCE_PUBLIC = 'public';

    public const string AUDIENCE_INTERNAL = 'internal';

    public const string AUDIENCE_DEPARTMENTAL = 'departmental';

    public const array AUDIENCES = [
        self::AUDIENCE_PUBLIC,
        self::AUDIENCE_INTERNAL,
        self::AUDIENCE_DEPARTMENTAL,
    ];

    protected $table = 'intranet_message_boards';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'title',
        'slug',
        'description',
        'audience',
        'department_id',
        'is_pinned',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<IntranetBoardPost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(IntranetBoardPost::class, 'intranet_message_board_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function isAccessibleBy(User $user): bool
    {
        if ($user->can('intranet.manage')) {
            return true;
        }

        if ($this->audience === self::AUDIENCE_PUBLIC || $this->audience === self::AUDIENCE_INTERNAL) {
            return true;
        }

        if ($this->audience === self::AUDIENCE_DEPARTMENTAL) {
            if (! $this->department_id) {
                return false;
            }

            if (isset($user->department_id) && (int) $user->department_id === (int) $this->department_id) {
                return true;
            }

            if (class_exists(\App\Modules\HrmsCore\Models\Employees\Employee::class)) {
                $employee = \App\Modules\HrmsCore\Models\Employees\Employee::where('user_id', $user->id)->first();
                if ($employee) {
                    return $employee->departments()
                        ->where('hrms_departments.id', $this->department_id)
                        ->exists();
                }
            }

            return false;
        }

        return false;
    }

    /**
     * Scope query to boards accessible by the given user.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->can('intranet.manage')) {
            return $query;
        }

        $deptIds = [];
        if (isset($user->department_id)) {
            $deptIds[] = (int) $user->department_id;
        }

        if (class_exists(\App\Modules\HrmsCore\Models\Employees\Employee::class)) {
            $employee = \App\Modules\HrmsCore\Models\Employees\Employee::where('user_id', $user->id)->first();
            if ($employee) {
                $deptIds = array_merge($deptIds, $employee->departments()->pluck('hrms_departments.id')->all());
            }
        }

        $deptIds = array_values(array_unique(array_filter($deptIds)));

        return $query->where(function (Builder $q) use ($deptIds): void {
            $q->whereIn('audience', [self::AUDIENCE_PUBLIC, self::AUDIENCE_INTERNAL]);
            if (! empty($deptIds)) {
                $q->orWhere(function (Builder $dq) use ($deptIds): void {
                    $dq->where('audience', self::AUDIENCE_DEPARTMENTAL)
                        ->whereIn('department_id', $deptIds);
                });
            }
        });
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->title).'-'.mb_strtolower(Str::random(6));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'department_id' => 'integer',
        ];
    }
}
