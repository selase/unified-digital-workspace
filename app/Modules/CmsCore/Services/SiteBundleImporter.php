<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\Menu;
use App\Modules\CmsCore\Models\MenuItem;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostMeta;
use App\Modules\CmsCore\Models\PostType;
use App\Modules\CmsCore\Models\Setting;
use App\Services\Tenancy\HybridCapacityService;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use App\Services\Tenancy\TenantStorageManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Imports an audited legacy-site bundle (pages, checksummed assets, theme settings) into a tenant.
 *
 * Reruns are idempotent; a page edited in the CMS since import is never overwritten.
 *
 * Bundle directory layout: bundle.json plus assets/<file>. Page bodies reference media as {{asset:<key>}}.
 */
final class SiteBundleImporter
{
    private const META_KEY = 'site_bundle_import';

    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantDatabaseManager $databases,
        private readonly TenantStorageManager $storage,
        private readonly HybridCapacityService $capacity,
    ) {}

    /**
     * @return array{pages: int, media: int, bytes: int}
     *
     * @throws RuntimeException When the bundle, tenant or existing content fails validation.
     */
    public function import(Tenant $tenant, User $editor, string $directory): array
    {
        $bundle = $this->loadBundle($directory);
        if ($tenant->slug !== $bundle['tenant']['slug']
            || $tenant->custom_domain !== $bundle['tenant']['domain']
            || $tenant->isolation_mode !== 'shared'
            || ($tenant->meta['site_bundle'] ?? null) !== $bundle['tenant']['slug']
            || ! $tenant->users()->whereKey($editor->id)->exists()) {
            throw new RuntimeException('Tenant does not match the bundle slug, domain or import marker, or the editor is not a member.');
        }

        $previous = $this->context->getTenant();
        $createdPaths = [];
        $committed = false;
        $this->databases->activate($tenant);
        $this->storage->configure($tenant);

        try {
            foreach (Post::query()->whereIn('slug', array_column($bundle['pages'], 'slug'))->get() as $page) {
                $fingerprint = PostMeta::query()->where('post_id', $page->id)->where('key', self::META_KEY)->value('value');
                if (($fingerprint['fingerprint'] ?? null) !== $this->fingerprint($page)) {
                    throw new RuntimeException('Existing page has editorial changes; refusing to overwrite it.');
                }
            }
            $this->capacity->auditStorage($tenant);

            $result = DB::connection('tenant')->transaction(function () use ($tenant, $editor, $bundle, $directory, &$createdPaths): array {
                $media = [];
                foreach ($bundle['assets'] as $asset) {
                    $media[$asset['key']] = $this->importAsset($tenant, $editor, $bundle['tenant']['slug'], $asset, $directory, $createdPaths);
                }
                $this->importPages($tenant, $editor, $bundle, $media);
                $this->importSettings($tenant, $bundle, $media);

                return ['pages' => count($bundle['pages']), 'media' => count($bundle['assets']), 'bytes' => array_sum(array_column($bundle['assets'], 'bytes'))];
            });
            $committed = true;
            $this->capacity->auditStorage($tenant);
            $this->capacity->auditDatabase($tenant);

            return $result;
        } catch (Throwable $exception) {
            if (! $committed) {
                foreach ($createdPaths as $path) {
                    $this->capacity->deleteFile($tenant, 'tenant', $path);
                }
                $this->capacity->auditStorage($tenant);
            }
            throw $exception;
        } finally {
            if ($previous !== null && $previous->id !== $tenant->id) {
                $this->databases->activate($previous);
                $this->storage->configure($previous);
            } elseif ($previous === null) {
                $this->context->clear();
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadBundle(string $directory): array
    {
        $file = $directory.'/bundle.json';
        if (is_link($directory) || ! is_file($file)) {
            throw new RuntimeException('Bundle directory or bundle.json is missing.');
        }
        $bundle = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        if (($bundle['version'] ?? null) !== 1 || ! isset($bundle['tenant']['slug'], $bundle['tenant']['domain'], $bundle['pages'], $bundle['assets'])) {
            throw new RuntimeException('Unsupported bundle format.');
        }
        foreach ($bundle['assets'] as $asset) {
            $path = $directory.'/assets/'.$asset['file'];
            if (! preg_match('/\A[A-Za-z0-9._@-]+\z/', $asset['file']) || is_link($path) || ! is_file($path)
                || filesize($path) !== $asset['bytes'] || hash_file('sha256', $path) !== $asset['sha256']) {
                throw new RuntimeException("Bundled asset {$asset['file']} differs from the audited source.");
            }
        }

        return $bundle;
    }

    /**
     * @param  array<string, mixed>  $asset
     * @param  list<string>  $createdPaths
     */
    private function importAsset(Tenant $tenant, User $editor, string $slug, array $asset, string $directory, array &$createdPaths): Media
    {
        $path = 'cms/'.$slug.'/'.$asset['sha256'].'-'.$asset['file'];
        if (Storage::disk('tenant')->exists($path)) {
            if (hash('sha256', (string) Storage::disk('tenant')->get($path)) !== $asset['sha256']) {
                throw new RuntimeException('Existing file conflicts with the source checksum.');
            }
        } else {
            $this->capacity->writeFile($tenant, 'tenant', $path, (string) file_get_contents($directory.'/assets/'.$asset['file']));
            $createdPaths[] = $path;
        }
        $size = @getimagesize($directory.'/assets/'.$asset['file']);
        $media = Media::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'path' => $path],
            [
                'disk' => 'tenant', 'original_filename' => $asset['file'], 'filename' => basename($path),
                'extension' => pathinfo($asset['file'], PATHINFO_EXTENSION), 'mime_type' => $size['mime'] ?? 'application/octet-stream',
                'size_bytes' => $asset['bytes'], 'checksum_sha256' => $asset['sha256'],
                'width' => $size[0] ?? null, 'height' => $size[1] ?? null, 'uploaded_by' => $editor->uuid,
                'source' => 'site-bundle', 'is_public' => true, 'alt_text' => $asset['alt'] ?? null,
                'metadata' => ['legacy_source' => $asset['source'] ?? null],
            ],
        );
        if ($media->disk !== 'tenant' || $media->checksum_sha256 !== $asset['sha256']) {
            throw new RuntimeException('Existing media ownership or checksum conflicts.');
        }

        return $media;
    }

    /**
     * @param  array<string, mixed>  $bundle
     * @param  array<string, Media>  $media
     */
    private function importPages(Tenant $tenant, User $editor, array $bundle, array $media): void
    {
        $replacements = [];
        foreach ($media as $key => $item) {
            $replacements['{{asset:'.$key.'}}'] = $item->serveUrl(false);
        }
        $type = PostType::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'page'],
            ['name' => 'Pages', 'is_active' => true, 'supports' => ['title', 'editor', 'excerpt', 'revisions']],
        );
        $menu = Menu::query()->firstOrCreate(['tenant_id' => $tenant->id, 'slug' => 'main-navigation'], ['name' => 'Main navigation']);

        foreach ($bundle['pages'] as $index => $source) {
            $page = Post::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $source['slug'], 'post_type_id' => $type->id],
                [
                    'title' => $source['title'], 'body' => strtr($source['body'], $replacements), 'excerpt' => $source['excerpt'] ?? null,
                    'author_id' => $editor->uuid, 'status' => 'published', 'audience' => 'public',
                    'published_at' => now(), 'sort_order' => $index,
                ],
            );
            PostMeta::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'post_id' => $page->id, 'key' => self::META_KEY],
                ['value' => ['fingerprint' => $this->fingerprint($page)]],
            );
            if ($source['menu'] ?? true) {
                MenuItem::query()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'menu_id' => $menu->id, 'post_id' => $page->id],
                    ['label' => $source['menu_label'] ?? $source['title'], 'url' => $source['slug'] === 'home' ? '/' : '/'.$source['slug'], 'sort_order' => $index],
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $bundle
     * @param  array<string, Media>  $media
     */
    private function importSettings(Tenant $tenant, array $bundle, array $media): void
    {
        $settings = $bundle['settings'] ?? [];
        foreach (['logo_media_id' => 'logo', 'favicon_media_id' => 'favicon'] as $setting => $assetKey) {
            if (isset($media[$assetKey])) {
                $settings[$setting] = $media[$assetKey]->id;
            }
        }
        foreach ($bundle['settings_media'] ?? [] as $setting => $assetKeys) {
            $settings[$setting] = implode(',', array_map(fn (string $assetKey): int => $media[$assetKey]->id, $assetKeys));
        }
        $settings += ['active_theme' => 'default'];
        foreach ($settings as $key => $value) {
            Setting::query()->firstOrCreate(['tenant_id' => $tenant->id, 'group' => 'theme', 'key' => $key], ['value' => $value]);
        }
    }

    private function fingerprint(Post $page): string
    {
        return hash('sha256', json_encode([$page->title, $page->body, $page->status, $page->audience], JSON_THROW_ON_ERROR));
    }
}
