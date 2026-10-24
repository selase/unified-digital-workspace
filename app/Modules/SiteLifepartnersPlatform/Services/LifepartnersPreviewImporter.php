<?php

declare(strict_types=1);

namespace App\Modules\SiteLifepartnersPlatform\Services;

use App\Models\Tenant;
use App\Models\TenantBillingAgreement;
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

final class LifepartnersPreviewImporter
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantDatabaseManager $databases,
        private readonly TenantStorageManager $storage,
        private readonly HybridCapacityService $capacity,
    ) {}

    /** @return array{pages: int, media: int, bytes: int} */
    public function import(Tenant $tenant, User $editor): array
    {
        $isLaunch = ($tenant->meta['migration_launch'] ?? null) === 'lifepartners'
            && $tenant->custom_domain === 'lifepartnersplatform.org';
        $isLocalPreview = app()->environment(['local', 'testing'])
            && ($tenant->meta['migration_preview'] ?? null) === 'lifepartners'
            && preg_match('/\A[a-z0-9.-]+\.test\z/', (string) $tenant->custom_domain) === 1;
        if (! ($isLaunch || $isLocalPreview)
            || $tenant->slug !== 'lifepartners-platform'
            || $tenant->isolation_mode !== 'shared'
            || TenantBillingAgreement::query()->where('tenant_id', $tenant->id)->exists()
            || ! $tenant->users()->whereKey($editor->id)->exists()) {
            throw new RuntimeException('Import requires an unbilled shared Lifepartners tenant (local .test preview or its launch domain) and a tenant member.');
        }

        $directory = dirname(__DIR__).'/Database/Seeders';
        $bundle = json_decode((string) file_get_contents($directory.'/lifepartners.json'), true, 512, JSON_THROW_ON_ERROR);
        if ($bundle['version'] !== 1 || count($bundle['pages']) !== 6 || count($bundle['assets']) !== 9) {
            throw new RuntimeException('Unexpected Lifepartners source inventory.');
        }
        foreach ($bundle['assets'] as $asset) {
            if (! preg_match('/\A[a-f0-9]{64}\.(?:png|jpe?g)\z/', $asset['file'])) {
                throw new RuntimeException('Invalid bundled asset path.');
            }
            $file = $directory.'/assets/'.$asset['file'];
            if (is_link($file) || ! is_file($file) || filesize($file) !== $asset['bytes']
                || hash_file('sha256', $file) !== $asset['sha256']) {
                throw new RuntimeException('Bundled asset checksum differs from audited source.');
            }
        }

        $previous = $this->context->getTenant();
        $createdPaths = [];
        $committed = false;
        $this->databases->activate($tenant);
        $this->storage->configure($tenant);
        try {
            foreach (Post::query()->whereIn('slug', array_column($bundle['pages'], 'slug'))->get() as $page) {
                $fingerprint = PostMeta::query()->where('post_id', $page->id)
                    ->where('key', 'lifepartners_import')->value('value');
                if (($fingerprint['fingerprint'] ?? null) !== $this->fingerprint($page)) {
                    throw new RuntimeException('Existing page has editorial changes; refusing to overwrite it.');
                }
            }
            $this->capacity->auditStorage($tenant);

            $result = DB::connection('tenant')->transaction(function () use ($tenant, $editor, $bundle, $directory, &$createdPaths): array {
                $urls = [];
                foreach ($bundle['assets'] as $asset) {
                    $path = 'cms/lifepartners/'.$asset['file'];
                    if (Storage::disk('tenant')->exists($path)) {
                        if (hash('sha256', (string) Storage::disk('tenant')->get($path)) !== $asset['sha256']) {
                            throw new RuntimeException('Existing preview file conflicts with the source checksum.');
                        }
                    } else {
                        $this->capacity->writeFile($tenant, 'tenant', $path, (string) file_get_contents($directory.'/assets/'.$asset['file']));
                        $createdPaths[] = $path;
                    }
                    $size = @getimagesize($directory.'/assets/'.$asset['file']);
                    $media = Media::query()->firstOrCreate(
                        ['tenant_id' => $tenant->id, 'path' => $path],
                        [
                            'disk' => 'tenant', 'original_filename' => basename($asset['source_path']),
                            'filename' => $asset['file'], 'extension' => pathinfo($asset['file'], PATHINFO_EXTENSION),
                            'mime_type' => $size['mime'] ?? 'application/octet-stream',
                            'size_bytes' => $asset['bytes'], 'checksum_sha256' => $asset['sha256'],
                            'width' => $size[0] ?? null, 'height' => $size[1] ?? null,
                            'uploaded_by' => $editor->uuid, 'source' => 'lifepartners-preview',
                            'is_public' => true, 'metadata' => ['legacy_path' => $asset['source_path']],
                        ],
                    );
                    if ($media->disk !== 'tenant' || $media->checksum_sha256 !== $asset['sha256']) {
                        throw new RuntimeException('Existing media ownership or checksum conflicts.');
                    }
                    $urls['__LPP_ASSET_'.$asset['sha256'].'__'] = $media->serveUrl(false);
                }
                $type = PostType::query()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => 'page'],
                    ['name' => 'Pages', 'is_active' => true, 'supports' => ['title', 'editor', 'excerpt', 'revisions']],
                );
                $menu = Menu::query()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => 'main-navigation'], ['name' => 'Main navigation'],
                );
                foreach ($bundle['pages'] as $index => $source) {
                    $page = Post::query()->firstOrCreate(
                        ['tenant_id' => $tenant->id, 'slug' => $source['slug'], 'post_type_id' => $type->id],
                        [
                            'title' => $source['title'], 'body' => strtr($source['body'], $urls),
                            'author_id' => $editor->uuid, 'status' => 'published',
                            'audience' => 'public', 'published_at' => now(), 'sort_order' => $index,
                        ],
                    );
                    PostMeta::query()->firstOrCreate(
                        ['tenant_id' => $tenant->id, 'post_id' => $page->id, 'key' => 'lifepartners_import'],
                        ['value' => ['fingerprint' => $this->fingerprint($page), 'source_sha256' => $source['source_sha256']]],
                    );
                    MenuItem::query()->firstOrCreate(
                        ['tenant_id' => $tenant->id, 'menu_id' => $menu->id, 'post_id' => $page->id],
                        ['label' => $source['title'], 'url' => $source['slug'] === 'home' ? '/' : '/'.$source['slug'], 'sort_order' => $index],
                    );
                }
                foreach (['site_name' => 'Life Partners Platform', 'site_tagline' => 'Saving lives and building resilience', 'active_theme' => 'default'] as $key => $value) {
                    Setting::query()->firstOrCreate(
                        ['tenant_id' => $tenant->id, 'group' => 'theme', 'key' => $key], ['value' => $value],
                    );
                }

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

    private function fingerprint(Post $page): string
    {
        return hash('sha256', json_encode([$page->title, $page->body, $page->status, $page->audience], JSON_THROW_ON_ERROR));
    }
}
