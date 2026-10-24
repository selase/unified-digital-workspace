<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\MenuItem;
use App\Modules\CmsCore\Models\Post;
use App\Modules\SiteLifepartnersPlatform\Services\LifepartnersPreviewImporter;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    config(['filesystems.disks.local.root' => Storage::disk('local')->path('')]);
    $this->editor = User::factory()->create();
    $this->preview = setActiveTenantForTest($this->editor, [
        'slug' => 'lifepartners-platform',
        'custom_domain' => 'lifepartners-preview.unified-digital-workspace.test',
        'custom_domain_status' => 'active',
        'meta' => ['migration_preview' => 'lifepartners'],
    ]);
});

it('imports editable content and original images without duplicating reruns', function (): void {
    $service = app(LifepartnersPreviewImporter::class);
    $first = $service->import($this->preview, $this->editor);
    $ids = Post::query()->pluck('id', 'slug')->all();
    $second = $service->import($this->preview, $this->editor);
    expect($first['pages'])->toBe(6)->and($first['media'])->toBe(9)
        ->and($second['pages'])->toBe(6)->and(Post::query()->count())->toBe(6)
        ->and(Media::query()->count())->toBe(9)->and(MenuItem::query()->count())->toBe(6)
        ->and(Post::query()->pluck('id', 'slug')->all())->toBe($ids);
    foreach (Media::query()->get() as $media) {
        expect($media->disk)->toBe('tenant')
            ->and(hash('sha256', Storage::disk('tenant')->get($media->path)))->toBe($media->checksum_sha256);
    }
    expect(Post::query()->where('slug', 'our-team')->firstOrFail()->body)->toContain('Abdul-Rauf Zakaria')
        ->and(Post::query()->where('slug', 'home')->firstOrFail()->body)->not->toContain('__LPP_ASSET_')
        ->and(Post::query()->where('slug', 'contact')->firstOrFail()->body)->not->toContain('<form');
});

it('keeps preview files local even when shared S3 is configured', function (): void {
    config(['filesystems.disks.s3.key' => 'synthetic-key', 'filesystems.disks.s3.bucket' => 'synthetic-bucket']);
    app(LifepartnersPreviewImporter::class)->import($this->preview, $this->editor);
    expect(config('filesystems.disks.tenant.driver'))->toBe('local')
        ->and(config('filesystems.disks.tenant.root'))->toContain('/previews/tenants/lifepartners-platform');
});

it('refuses production execution before writing content', function (): void {
    $this->app->instance('env', 'production');
    expect(fn () => app(LifepartnersPreviewImporter::class)->import($this->preview, $this->editor))->toThrow(RuntimeException::class);
    expect(Post::query()->count())->toBe(0)->and(Media::query()->count())->toBe(0);
});

it('refuses non-preview customers and real domains', function (): void {
    $this->preview->update(['meta' => [], 'custom_domain' => 'lifepartnersplatform.org']);
    expect(fn () => app(LifepartnersPreviewImporter::class)->import($this->preview, $this->editor))->toThrow(RuntimeException::class);
    expect(Post::query()->count())->toBe(0);
});

it('requires the selected editor to belong to this tenant', function (): void {
    $foreign = User::factory()->create();
    expect(fn () => app(LifepartnersPreviewImporter::class)->import($this->preview, $foreign))->toThrow(RuntimeException::class);
    expect(Post::query()->count())->toBe(0);
});

it('preserves editorial changes on rerun', function (): void {
    $service = app(LifepartnersPreviewImporter::class);
    $service->import($this->preview, $this->editor);
    $page = Post::query()->where('slug', 'about')->firstOrFail();
    $page->update(['body' => '<p>Reviewed editorial update</p>']);
    expect(fn () => $service->import($this->preview, $this->editor))->toThrow(RuntimeException::class);
    expect($page->fresh()->body)->toContain('Reviewed editorial update')
        ->and(Post::query()->count())->toBe(6)->and(Media::query()->count())->toBe(9);
});

it('supports a no-write command dry run and refuses live-domain execution', function (): void {
    $this->artisan('cms:lifepartners-preview')->assertSuccessful();
    $this->artisan('cms:lifepartners-preview', ['--execute' => true, '--editor' => $this->editor->email, '--domain' => 'lifepartnersplatform.org'])->assertFailed();
    expect(Post::query()->count())->toBe(0)->and(Media::query()->count())->toBe(0);
});

it('serves every imported legacy page and checksums while rejecting foreign media access', function (): void {
    foreach (['cms-core', 'site-lifepartners-platform'] as $module) {
        App\Models\TenantModule::query()->create(['tenant_id' => $this->preview->id, 'module_slug' => $module, 'is_enabled' => true, 'version' => '1.0.0']);
    }
    app(LifepartnersPreviewImporter::class)->import($this->preview, $this->editor);
    $origin = 'https://'.$this->preview->custom_domain;
    foreach (['/', '/about', '/what-we-do', '/contact', '/our-team', '/areas-of-operation'] as $path) {
        $this->get($origin.$path)->assertOk()->assertSee('Life Partners Platform')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
    $this->get($origin.'/missing-source-page')->assertNotFound();
    $media = Media::query()->firstOrFail();
    $mediaPath = $media->serveUrl(false);
    $this->get($origin.$mediaPath)->assertOk();

    $other = App\Models\Tenant::factory()->create(['custom_domain' => 'another-preview.test', 'custom_domain_status' => 'active', 'isolation_mode' => 'shared']);
    App\Models\TenantModule::query()->create(['tenant_id' => $other->id, 'module_slug' => 'cms-core', 'is_enabled' => true, 'version' => '1.0.0']);
    $this->get('https://another-preview.test'.$mediaPath)->assertNotFound();
});

it('lets the preview editor update CMS content without granting billing or user administration', function (): void {
    $this->artisan('cms:lifepartners-preview', ['--execute' => true, '--editor' => $this->editor->email])->assertSuccessful();
    $this->editor->unsetRelation('roles')->unsetRelation('permissions');
    expect($this->editor->can('cms.posts.update'))->toBeTrue()
        ->and($this->editor->can('core.users.create'))->toBeFalse()
        ->and($this->editor->can('cms.settings.manage'))->toBeFalse();
    $page = Post::query()->where('slug', 'about')->firstOrFail();
    $this->actingAs($this->editor)->put(route('cms-core.posts.update', $page), ['body' => '<p>Verified CMS editor update</p>'])
        ->assertRedirect();
    expect($page->fresh()->body)->toContain('Verified CMS editor update');
});

it('rejects dedicated connections and adopted customer agreements before import', function (bool $adopted): void {
    if ($adopted) {
        App\Models\TenantBillingAgreement::factory()->create(['tenant_id' => $this->preview->id]);
    } else {
        $this->preview->update(['isolation_mode' => 'db_per_tenant']);
    }
    expect(fn () => app(LifepartnersPreviewImporter::class)->import($this->preview, $this->editor))
        ->toThrow(RuntimeException::class, 'unbilled local shared');
    $this->artisan('cms:lifepartners-preview', ['--execute' => true, '--editor' => $this->editor->email])->assertFailed();
    expect(Post::query()->count())->toBe(0)->and(Media::query()->count())->toBe(0);
})->with([true, false]);
