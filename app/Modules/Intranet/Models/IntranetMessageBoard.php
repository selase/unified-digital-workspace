<?php

declare(strict_types=1);

namespace App\Modules\Intranet\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class IntranetMessageBoard extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'intranet_message_boards';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'title',
        'slug',
        'description',
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
        ];
    }
}
