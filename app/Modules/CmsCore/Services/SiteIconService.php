<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Services;

use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\MediaVariant;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves and resizes the master site icon into platform-specific PNGs.
 *
 * The Media row supplied by the tenant is treated as a single high-res square
 * source. Resized variants are cached as MediaVariant rows so subsequent
 * requests stream straight from storage without re-running GD.
 */
final class SiteIconService
{
    /** Sizes referenced by link tags and the web manifest. */
    public const SIZES = [16, 32, 96, 180, 192, 512];

    /** MIME types we can resize with GD. */
    private const RESIZABLE_MIMES = [
        'image/png',
        'image/jpeg',
        'image/jpg',
        'image/gif',
        'image/webp',
    ];

    public function __construct(
        private readonly CmsThemeService $themeService
    ) {}

    /**
     * Returns the active tenant's site icon, or null when none configured.
     */
    public function source(): ?Media
    {
        return $this->themeService->siteIcon();
    }

    /**
     * Build (or fetch from cache) a resized PNG variant for the given size.
     *
     * Returns the raw PNG bytes ready to stream back to the browser. When
     * the source is unsupported (e.g. SVG), the original bytes are
     * returned instead — browsers handle the scaling.
     *
     * @return array{contents: string, mime: string}|null
     */
    public function variantBytes(int $size): ?array
    {
        $source = $this->source();

        if (! $source) {
            return null;
        }

        $sourceDisk = Storage::disk($source->disk);

        if (! $sourceDisk->exists($source->path)) {
            return null;
        }

        if (! $this->canResize($source)) {
            return [
                'contents' => (string) $sourceDisk->get($source->path),
                'mime' => $source->mime_type ?: 'application/octet-stream',
            ];
        }

        $variantName = "site-icon-{$size}";
        $cached = MediaVariant::query()
            ->where('media_id', $source->id)
            ->where('variant', $variantName)
            ->first();

        if ($cached && Storage::disk($cached->disk)->exists($cached->path)) {
            return [
                'contents' => (string) Storage::disk($cached->disk)->get($cached->path),
                'mime' => $cached->mime_type ?: 'image/png',
            ];
        }

        $resized = $this->resizeToPng((string) $sourceDisk->get($source->path), $source->mime_type ?: '', $size);

        if ($resized === null) {
            return null;
        }

        $variantPath = "cms/media/variants/{$source->id}-{$variantName}.png";
        Storage::disk($source->disk)->put($variantPath, $resized);

        MediaVariant::query()->updateOrCreate(
            ['media_id' => $source->id, 'variant' => $variantName],
            [
                'disk' => $source->disk,
                'path' => $variantPath,
                'width' => $size,
                'height' => $size,
                'size_bytes' => mb_strlen($resized),
                'mime_type' => 'image/png',
            ]
        );

        return ['contents' => $resized, 'mime' => 'image/png'];
    }

    /**
     * Compose the manifest payload (icons + theme/background colors).
     *
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        $icons = [];
        $source = $this->source();

        if ($source) {
            foreach ([192, 512] as $size) {
                $icons[] = [
                    'src' => route('site-icon.serve', ['size' => $size]),
                    'sizes' => "{$size}x{$size}",
                    'type' => 'image/png',
                    'purpose' => 'any',
                ];
            }
        }

        return [
            'name' => $this->themeService->siteName(),
            'short_name' => mb_substr($this->themeService->siteName(), 0, 12),
            'icons' => $icons,
            'theme_color' => $this->themeService->themeColor(),
            'background_color' => '#ffffff',
            'display' => 'standalone',
            'start_url' => '/',
        ];
    }

    /**
     * Builds a cache-busting query value derived from the source media id.
     * Lets us pin Cache-Control: immutable while still invalidating when
     * the tenant swaps icons.
     */
    public function cacheBuster(): ?string
    {
        $source = $this->source();

        return $source ? (string) $source->id : null;
    }

    private function canResize(Media $source): bool
    {
        return in_array(mb_strtolower($source->mime_type ?? ''), self::RESIZABLE_MIMES, true);
    }

    private function resizeToPng(string $sourceBytes, string $mime, int $size): ?string
    {
        $src = match (mb_strtolower($mime)) {
            'image/png' => @imagecreatefromstring($sourceBytes),
            'image/jpeg', 'image/jpg' => @imagecreatefromstring($sourceBytes),
            'image/gif' => @imagecreatefromstring($sourceBytes),
            'image/webp' => @imagecreatefromstring($sourceBytes),
            default => false,
        };

        if (! $src) {
            return null;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $square = min($srcW, $srcH);
        $offsetX = (int) (($srcW - $square) / 2);
        $offsetY = (int) (($srcH - $square) / 2);

        $dst = imagecreatetruecolor($size, $size);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $size, $size, $transparent);

        imagecopyresampled($dst, $src, 0, 0, $offsetX, $offsetY, $size, $size, $square, $square);

        ob_start();
        imagepng($dst);
        $bytes = (string) ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        return $bytes !== '' ? $bytes : null;
    }
}
