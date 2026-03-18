<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Services;

use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostMeta;
use Illuminate\Support\Facades\Storage;

final class CmsSeoService
{
    /**
     * Get SEO data for a post, falling back to post fields.
     *
     * @return array{title: string, description: string, og_image: string|null, canonical: string|null}
     */
    public function forPost(Post $post, string $siteName = ''): array
    {
        $meta = $this->getMetaValues($post);

        $title = $meta['seo_title'] ?: $post->title;
        if ($siteName !== '') {
            $title .= ' — '.$siteName;
        }

        $ogImage = null;
        if ($post->featuredMedia) {
            $ogImage = Storage::disk($post->featuredMedia->disk)->url($post->featuredMedia->path);
        }

        return [
            'title' => $title,
            'description' => $meta['seo_description'] ?: ($post->excerpt ?? ''),
            'og_image' => $ogImage,
            'canonical' => $meta['seo_canonical'] ?: null,
        ];
    }

    /**
     * Save SEO meta values for a post.
     *
     * @param  array{seo_title?: string|null, seo_description?: string|null, seo_canonical?: string|null}  $values
     */
    public function saveForPost(Post $post, array $values): void
    {
        foreach (['seo_title', 'seo_description', 'seo_canonical'] as $key) {
            $value = $values[$key] ?? null;

            if ($value !== null && $value !== '') {
                PostMeta::query()->updateOrCreate(
                    ['post_id' => $post->id, 'key' => $key],
                    ['value' => $value]
                );
            } else {
                PostMeta::query()
                    ->where('post_id', $post->id)
                    ->where('key', $key)
                    ->delete();
            }
        }
    }

    /**
     * @return array{seo_title: string, seo_description: string, seo_canonical: string}
     */
    public function getMetaValues(Post $post): array
    {
        $meta = PostMeta::query()
            ->where('post_id', $post->id)
            ->whereIn('key', ['seo_title', 'seo_description', 'seo_canonical'])
            ->pluck('value', 'key');

        return [
            'seo_title' => (string) ($meta['seo_title'] ?? ''),
            'seo_description' => (string) ($meta['seo_description'] ?? ''),
            'seo_canonical' => (string) ($meta['seo_canonical'] ?? ''),
        ];
    }
}
