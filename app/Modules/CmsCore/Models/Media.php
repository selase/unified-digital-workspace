<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Models;

use App\Models\User;
use App\Modules\CmsCore\Database\Factories\MediaFactory;
use App\Modules\CmsCore\Models\Concerns\HasCmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $disk
 * @property string $path
 * @property string $original_filename
 * @property string $filename
 * @property string|null $extension
 * @property string $mime_type
 * @property int $size_bytes
 * @property string|null $checksum_sha256
 * @property int|null $width
 * @property int|null $height
 * @property float|null $duration_seconds
 * @property int|null $bitrate
 * @property float|null $fps
 * @property string|null $dominant_color
 * @property string|null $blurhash
 * @property array<string, mixed>|null $metadata
 * @property string|null $alt_text
 * @property string|null $caption
 * @property string|null $title
 * @property string|null $description
 * @property string $uploaded_by
 * @property string|null $source
 * @property bool $is_public
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @use HasFactory<MediaFactory>
 */
final class Media extends Model
{
    use BelongsToTenant;
    use HasCmsUuid;

    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'media';

    protected $connection = 'landlord';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'disk',
        'path',
        'original_filename',
        'filename',
        'extension',
        'mime_type',
        'size_bytes',
        'checksum_sha256',
        'width',
        'height',
        'duration_seconds',
        'bitrate',
        'fps',
        'dominant_color',
        'blurhash',
        'metadata',
        'alt_text',
        'caption',
        'title',
        'description',
        'uploaded_by',
        'source',
        'is_public',
    ];

    /**
     * @return HasMany<MediaVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class, 'media_id');
    }

    /**
     * @return BelongsToMany<Post, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'media_post')
            ->withPivot('role', 'sort_order')
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return Factory<static>
     */
    protected static function newFactory(): Factory
    {
        return MediaFactory::new();
    }

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration_seconds' => 'float',
            'bitrate' => 'integer',
            'fps' => 'float',
            'metadata' => 'array',
            'is_public' => 'boolean',
        ];
    }
}
