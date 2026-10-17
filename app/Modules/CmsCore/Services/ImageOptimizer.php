<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Prepares uploaded photos for the web before they are stored.
 *
 * A camera or phone image is often several megabytes and far larger than any
 * screen. Uploads are scaled down to a sensible maximum and re-encoded, which
 * typically cuts the file to a fraction of its size with no visible change.
 * Re-encoding also drops embedded metadata such as GPS location.
 *
 * Anything that cannot be improved safely is stored exactly as uploaded:
 * non-images, GIF and SVG, animated PNG/WebP, and files that would come out
 * larger than they went in.
 */
final class ImageOptimizer
{
    /** Longest edge, in pixels, an image is allowed to keep. */
    public const MAX_EDGE = 2560;

    private const JPEG_QUALITY = 82;

    private const WEBP_QUALITY = 82;

    /** Originals with more pixels than this are stored untouched, whatever memory is available. */
    private const MAX_SOURCE_PIXELS = 60_000_000;

    /** GD holds a decoded image at about 4 bytes per pixel; 5 leaves room for its own overhead. */
    private const BYTES_PER_PIXEL = 5;

    /** A re-encode that saves less than this share of the file is not worth the quality loss. */
    private const MIN_SAVING = 0.05;

    /**
     * Store an upload on a disk, optimized when that is possible and worthwhile.
     *
     * @return array{path: string, size_bytes: int, width: int|null, height: int|null, checksum_sha256: string|null, optimized: bool}
     */
    public function store(UploadedFile $file, string $directory, string $disk = 'tenant'): array
    {
        $optimized = $this->optimize($file);

        if ($optimized === null) {
            $dimensions = @getimagesize((string) $file->getRealPath());

            return [
                'path' => (string) $file->store($directory, $disk),
                'size_bytes' => (int) $file->getSize(),
                'width' => $dimensions === false ? null : $dimensions[0],
                'height' => $dimensions === false ? null : $dimensions[1],
                'checksum_sha256' => hash_file('sha256', (string) $file->getRealPath()) ?: null,
                'optimized' => false,
            ];
        }

        $path = mb_trim($directory, '/').'/'.$file->hashName();
        Storage::disk($disk)->put($path, $optimized['contents']);

        return [
            'path' => $path,
            'size_bytes' => mb_strlen($optimized['contents'], '8bit'),
            'width' => $optimized['width'],
            'height' => $optimized['height'],
            'checksum_sha256' => hash('sha256', $optimized['contents']),
            'optimized' => true,
        ];
    }

    /**
     * @return array{contents: string, width: int, height: int}|null Null when the file should be stored as uploaded.
     */
    public function optimize(UploadedFile $file): ?array
    {
        $sourcePath = (string) $file->getRealPath();
        $info = @getimagesize($sourcePath);

        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return null;
        }

        [$sourceWidth, $sourceHeight, $type] = $info;

        if ($sourceWidth < 1 || $sourceHeight < 1 || ! $this->fitsInMemory($sourceWidth, $sourceHeight)) {
            return null;
        }

        $sourceBytes = (string) file_get_contents($sourcePath);

        if ($this->isAnimated($sourceBytes, $type)) {
            return null;
        }

        $image = @imagecreatefromstring($sourceBytes);

        if (! $image) {
            return null;
        }

        $rotated = false;

        if ($type === IMAGETYPE_JPEG) {
            [$image, $rotated] = $this->applyExifOrientation($image, $sourcePath);
        }

        [$width, $height] = [imagesx($image), imagesy($image)];
        $scale = min(1, self::MAX_EDGE / max($width, $height));
        $resized = $scale < 1;

        if ($resized) {
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));
            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
            $this->preserveTransparency($canvas, $type);
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
            imagedestroy($image);
            $image = $canvas;
            [$width, $height] = [$targetWidth, $targetHeight];
        } else {
            $this->preserveTransparency($image, $type);
        }

        $contents = $this->encode($image, $type);
        imagedestroy($image);

        if ($contents === '') {
            return null;
        }

        $saving = 1 - mb_strlen($contents, '8bit') / max(1, mb_strlen($sourceBytes, '8bit'));

        // A rotated or scaled image must be kept even if it saved little, or the stored file would be wrong.
        if (! $resized && ! $rotated && $saving < self::MIN_SAVING) {
            return null;
        }

        return ['contents' => $contents, 'width' => $width, 'height' => $height];
    }

    /**
     * Decoding needs the whole image in memory, plus the scaled copy. An upload
     * that would not fit under PHP's memory limit is stored as it is rather
     * than crashing the request.
     */
    private function fitsInMemory(int $width, int $height): bool
    {
        $pixels = $width * $height;

        if ($pixels > self::MAX_SOURCE_PIXELS) {
            return false;
        }

        $limit = ini_parse_quantity((string) ini_get('memory_limit'));

        if ($limit <= 0) {
            return true;
        }

        $scale = min(1, self::MAX_EDGE / max($width, $height));
        $needed = (int) (($pixels + $pixels * $scale * $scale) * self::BYTES_PER_PIXEL);

        return $needed < ($limit - memory_get_usage(true)) * 0.8;
    }

    private function encode(GdImage $image, int $type): string
    {
        ob_start();

        match ($type) {
            IMAGETYPE_JPEG => (function () use ($image): void {
                imageinterlace($image, true);
                imagejpeg($image, null, self::JPEG_QUALITY);
            })(),
            IMAGETYPE_PNG => imagepng($image, null, 9),
            IMAGETYPE_WEBP => imagewebp($image, null, self::WEBP_QUALITY),
            default => null,
        };

        return (string) ob_get_clean();
    }

    private function preserveTransparency(GdImage $image, int $type): void
    {
        if ($type === IMAGETYPE_JPEG) {
            return;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
    }

    /**
     * Phones store photos sideways with a note saying how to turn them. Since
     * re-encoding drops that note, the pixels are turned upright here instead.
     *
     * @return array{0: GdImage, 1: bool}
     */
    private function applyExifOrientation(GdImage $image, string $sourcePath): array
    {
        if (! function_exists('exif_read_data')) {
            return [$image, false];
        }

        $orientation = (int) (@exif_read_data($sourcePath)['Orientation'] ?? 1);

        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };

        if ($angle !== 0) {
            $turned = imagerotate($image, $angle, 0);

            if ($turned !== false) {
                imagedestroy($image);
                $image = $turned;
            }
        }

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        return [$image, $orientation > 1 && $orientation <= 8];
    }

    /**
     * GD keeps only the first frame, so animations are left alone.
     */
    private function isAnimated(string $bytes, int $type): bool
    {
        if ($type === IMAGETYPE_WEBP) {
            return str_contains(mb_substr($bytes, 0, 64, '8bit'), 'ANIM') || str_contains(mb_substr($bytes, 0, 64, '8bit'), 'VP8X') && (ord($bytes[20] ?? "\0") & 0x02) !== 0;
        }

        if ($type === IMAGETYPE_PNG) {
            $firstImageData = mb_strpos($bytes, 'IDAT', 0, '8bit');
            $animationControl = mb_strpos($bytes, 'acTL', 0, '8bit');

            return $animationControl !== false && ($firstImageData === false || $animationControl < $firstImageData);
        }

        return false;
    }
}
