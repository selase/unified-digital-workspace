<?php

declare(strict_types=1);

namespace App\Modules\Intranet\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class IntranetEvent extends Model
{
    use BelongsToTenant;
    use HasUuid;

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
