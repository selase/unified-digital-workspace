<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Models;

use App\Modules\CmsCore\Database\Factories\PostTypeFactory;
use App\Modules\CmsCore\Models\Concerns\HasCmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $icon
 * @property array<string, mixed>|null $supports
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @use HasFactory<PostTypeFactory>
 */
final class PostType extends Model
{
    use BelongsToTenant;
    use HasCmsUuid;

    /** @use HasFactory<PostTypeFactory> */
    use HasFactory;

    protected $table = 'post_types';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'slug',
        'description',
        'icon',
        'supports',
        'is_active',
    ];

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'post_type_id');
    }

    public function setSlugAttribute(string $value): void
    {
        $this->attributes['slug'] = Str::slug($value);
    }

    /**
     * @return Factory<static>
     */
    protected static function newFactory(): Factory
    {
        return PostTypeFactory::new();
    }

    protected function casts(): array
    {
        return [
            'supports' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
