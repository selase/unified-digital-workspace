<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Support;

use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\Post;

/** Finds the photo that should sit behind a page's banner: the page's own featured image, when it is landscape. */
final class PageHero
{
    public static function forSlug(string $slug): ?Media
    {
        $media = Post::query()->where('slug', $slug)->with('featuredMedia')->first()?->featuredMedia;

        return self::suits($media) ? $media : null;
    }

    public static function suits(?Media $media): bool
    {
        return $media !== null && (int) $media->width > (int) $media->height;
    }

    /** @return array{src: string, srcset: string} */
    public static function sources(Media $media): array
    {
        $base = route('cms-core.media.serve', $media);

        return [
            'src' => $base.'?width=1200',
            'srcset' => $base.'?width=768 768w, '.$base.'?width=1200 1200w',
        ];
    }
}
