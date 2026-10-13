<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Services;

use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\MediaVariant;
use Illuminate\Support\Facades\Storage;

/**
 * Small JPEG previews of library images for admin screens that show many at
 * once. A preview is generated on first request and cached as a MediaVariant
 * row beside the original, so each image is only ever resized once.
 */
final class MediaThumbnailService
{
    public const VARIANT = 'thumb';

    private const MAX_WIDTH = 480;

    private const JPEG_QUALITY = 78;

    /**
     * Originals larger than this are not decoded: GD needs roughly 4 bytes per
     * pixel in memory, so an oversized upload could exhaust the request.
     */
    private const MAX_SOURCE_PIXELS = 40_000_000;

    private const RESIZABLE_MIMES = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

    /**
     * @return array{contents: string, mime: string}|null Null when the original file is missing or cannot be read as an image.
     */
    public function bytes(Media $media): ?array
    {
        if (! in_array(mb_strtolower((string) $media->mime_type), self::RESIZABLE_MIMES, true)) {
            return null;
        }

        $cached = MediaVariant::query()
            ->where('media_id', $media->id)
            ->where('variant', self::VARIANT)
            ->first();

        if ($cached && Storage::disk($cached->disk)->exists($cached->path)) {
            return ['contents' => (string) Storage::disk($cached->disk)->get($cached->path), 'mime' => 'image/jpeg'];
        }

        $disk = Storage::disk($media->disk);

        if (! $disk->exists($media->path)) {
            return null;
        }

        $resized = $this->resize((string) $disk->get($media->path));

        if ($resized === null) {
            return null;
        }

        $path = "cms/media/variants/{$media->id}-".self::VARIANT.'.jpg';
        $disk->put($path, $resized['contents']);

        MediaVariant::query()->updateOrCreate(
            ['media_id' => $media->id, 'variant' => self::VARIANT],
            [
                'disk' => $media->disk,
                'path' => $path,
                'width' => $resized['width'],
                'height' => $resized['height'],
                'size_bytes' => mb_strlen($resized['contents'], '8bit'),
                'mime_type' => 'image/jpeg',
            ]
        );

        return ['contents' => $resized['contents'], 'mime' => 'image/jpeg'];
    }

    /**
     * @return array{contents: string, width: int, height: int}|null
     */
    private function resize(string $sourceBytes): ?array
    {
        $size = @getimagesizefromstring($sourceBytes);

        if ($size === false || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > self::MAX_SOURCE_PIXELS) {
            return null;
        }

        $source = @imagecreatefromstring($sourceBytes);

        if (! $source) {
            return null;
        }

        [$sourceWidth, $sourceHeight] = [imagesx($source), imagesy($source)];
        $width = min(self::MAX_WIDTH, $sourceWidth);
        $height = max(1, (int) round($sourceHeight * $width / $sourceWidth));

        $thumbnail = imagecreatetruecolor($width, $height);
        // JPEG has no transparency: flatten transparent PNG/WebP areas onto white instead of black.
        imagefilledrectangle($thumbnail, 0, 0, $width, $height, imagecolorallocate($thumbnail, 255, 255, 255));
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        ob_start();
        imagejpeg($thumbnail, null, self::JPEG_QUALITY);
        $bytes = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($thumbnail);

        return $bytes !== '' ? ['contents' => $bytes, 'width' => $width, 'height' => $height] : null;
    }
}
