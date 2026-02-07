<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Models;

use App\Modules\CmsCore\Database\Factories\TagFactory;
use App\Modules\CmsCore\Models\Concerns\HasCmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @use HasFactory<TagFactory>
 */
final class Tag extends Model
{
    use BelongsToTenant;
    use HasCmsUuid;

    /** @use HasFactory<TagFactory> */
    use HasFactory;

    protected $table = 'tags';

    protected $connection = 'landlord';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'slug',
        'description',
    ];

    /**
     * @return BelongsToMany<Post, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_tag')->withTimestamps();
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
        return TagFactory::new();
    }
}
