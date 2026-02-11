<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $media_id
 * @property string $variant
 * @property string $disk
 * @property string $path
 * @property int|null $width
 * @property int|null $height
 * @property int|null $size_bytes
 * @property string|null $mime_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class MediaVariant extends Model
{
    use UsesTenantConnection;

    protected $table = 'media_variants';

    protected $fillable = [
        'media_id',
        'variant',
        'disk',
        'path',
        'width',
        'height',
        'size_bytes',
        'mime_type',
    ];

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'size_bytes' => 'integer',
        ];
    }
}
