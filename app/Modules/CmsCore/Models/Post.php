<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Models;

use App\Models\User;
use App\Modules\CmsCore\Database\Factories\PostFactory;
use App\Modules\CmsCore\Models\Concerns\HasCmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $post_type_id
 * @property string $title
 * @property string $slug
 * @property string $status
 * @property string|null $excerpt
 * @property string $body
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $scheduled_for
 * @property string $author_id
 * @property string|null $editor_id
 * @property int|null $featured_media_id
 * @property int|null $parent_id
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @use HasFactory<PostFactory>
 */
final class Post extends Model
{
    use BelongsToTenant;
    use HasCmsUuid;

    /** @use HasFactory<PostFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'posts';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'post_type_id',
        'title',
        'slug',
        'status',
        'excerpt',
        'body',
        'published_at',
        'scheduled_for',
        'author_id',
        'editor_id',
        'featured_media_id',
        'parent_id',
        'sort_order',
    ];

    /**
     * @return BelongsTo<PostType, $this>
     */
    public function postType(): BelongsTo
    {
        return $this->belongsTo(PostType::class, 'post_type_id');
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_post')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag')->withTimestamps();
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_media_id');
    }

    /**
     * @return BelongsToMany<Media, $this>
     */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'media_post')
            ->withPivot('role', 'sort_order')
            ->withTimestamps();
    }

    /**
     * @return HasMany<PostRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PostRevision::class, 'post_id');
    }

    /**
     * @return HasMany<PostMeta, $this>
     */
    public function meta(): HasMany
    {
        return $this->hasMany(PostMeta::class, 'post_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function setSlugAttribute(string $value): void
    {
        $this->attributes['slug'] = Str::slug($value);
    }

    /**
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at');
    }

    /**
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', 'scheduled')
            ->whereNotNull('scheduled_for');
    }

    /**
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function scopeForType(Builder $query, string $slug): Builder
    {
        return $query->whereHas('postType', fn (Builder $typeQuery) => $typeQuery->where('slug', $slug));
    }

    /**
     * @return Factory<static>
     */
    protected static function newFactory(): Factory
    {
        return PostFactory::new();
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'sort_order' => 'integer',
        ];
    }
}
