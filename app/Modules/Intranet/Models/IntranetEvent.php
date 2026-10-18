<?php

declare(strict_types=1);

namespace App\Modules\Intranet\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
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
 * @property \Illuminate\Support\Carbon $event_date
 * @property string|null $start_time
 * @property string|null $end_time
 * @property string $location
 * @property string|null $organizer_id
 * @property string $category
 * @property bool $is_virtual
 * @property string|null $meeting_link
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, IntranetEventRsvp> $rsvps
 * @property-read User|null $organizer
 */
final class IntranetEvent extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const string CATEGORY_ALL_HANDS = 'all_hands';

    public const string CATEGORY_TRAINING = 'training';

    public const string CATEGORY_BOARD_MEETING = 'board_meeting';

    public const string CATEGORY_SOCIAL = 'social';

    public const string CATEGORY_DEPARTMENTAL = 'departmental';

    public const array CATEGORIES = [
        self::CATEGORY_ALL_HANDS,
        self::CATEGORY_TRAINING,
        self::CATEGORY_BOARD_MEETING,
        self::CATEGORY_SOCIAL,
        self::CATEGORY_DEPARTMENTAL,
    ];

    protected $table = 'intranet_events';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'title',
        'slug',
        'description',
        'event_date',
        'start_time',
        'end_time',
        'location',
        'organizer_id',
        'category',
        'is_virtual',
        'meeting_link',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id', 'uuid');
    }

    /**
     * @return HasMany<IntranetEventRsvp, $this>
     */
    public function rsvps(): HasMany
    {
        return $this->hasMany(IntranetEventRsvp::class, 'intranet_event_id');
    }

    public function userRsvp(?User $user): ?IntranetEventRsvp
    {
        if (! $user) {
            return null;
        }

        return $this->rsvps->firstWhere('user_id', (string) $user->uuid);
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
            'event_date' => 'date',
            'is_virtual' => 'boolean',
        ];
    }
}
