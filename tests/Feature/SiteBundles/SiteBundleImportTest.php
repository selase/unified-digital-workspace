<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\MenuItem;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\Setting;
use App\Modules\CmsCore\Services\SiteBundleImporter;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    config(['filesystems.disks.local.root' => Storage::disk('local')->path('')]);
    $this->editor = User::factory()->create();
});

function bundleTenant(User $editor, string $slug, string $domain): App\Models\Tenant
{
    return setActiveTenantForTest($editor, [
        'slug' => $slug, 'custom_domain' => $domain, 'custom_domain_status' => 'active',
        'meta' => ['site_bundle' => $slug, 'launched' => true],
    ]);
}

dataset('bundles', [
    'kunneo' => ['SiteKunneo', 'kunneo', 'kunneo.com', 5, 16],
    'readysetgo' => ['SiteReadysetgo', 'readysetgo', 'readysetgoghana.com', 3, 7],
]);

it('imports pages, checksummed media, menu and theme settings idempotently', function (string $module, string $slug, string $domain, int $pages, int $assets): void {
    $tenant = bundleTenant($this->editor, $slug, $domain);
    $directory = app_path('Modules/'.$module.'/Database/Seeders/bundle');
    $service = app(SiteBundleImporter::class);

    $first = $service->import($tenant, $this->editor, $directory);
    $service->import($tenant, $this->editor, $directory);

    expect($first['pages'])->toBe($pages)->and($first['media'])->toBe($assets)
        ->and(Post::query()->count())->toBe($pages)
        ->and(Media::query()->count())->toBe($assets)
        ->and(MenuItem::query()->count())->toBe($pages)
        ->and(Setting::query()->where('key', 'site_name')->count())->toBe(1)
        ->and(Setting::query()->where('key', 'logo_media_id')->count())->toBe(1);
    foreach (Media::query()->get() as $media) {
        expect(hash('sha256', Storage::disk('tenant')->get($media->path)))->toBe($media->checksum_sha256);
    }
    expect(Post::query()->get()->pluck('body')->implode(''))->not->toContain('{{asset:');
})->with('bundles');

it('refuses a tenant whose slug, domain or marker does not match the bundle', function (): void {
    $tenant = bundleTenant($this->editor, 'kunneo', 'kunneo.com');
    $tenant->update(['custom_domain' => 'another.example']);
    expect(fn () => app(SiteBundleImporter::class)->import($tenant, $this->editor, app_path('Modules/SiteKunneo/Database/Seeders/bundle')))
        ->toThrow(RuntimeException::class);

    $tenant->update(['custom_domain' => 'kunneo.com', 'meta' => []]);
    expect(fn () => app(SiteBundleImporter::class)->import($tenant, $this->editor, app_path('Modules/SiteKunneo/Database/Seeders/bundle')))
        ->toThrow(RuntimeException::class);
    expect(Post::query()->count())->toBe(0);
});

it('does not overwrite pages edited after import', function (): void {
    $tenant = bundleTenant($this->editor, 'readysetgo', 'readysetgoghana.com');
    $directory = app_path('Modules/SiteReadysetgo/Database/Seeders/bundle');
    app(SiteBundleImporter::class)->import($tenant, $this->editor, $directory);
    Post::query()->where('slug', 'aboutus')->update(['body' => '<p>Edited by the owner</p>']);

    expect(fn () => app(SiteBundleImporter::class)->import($tenant, $this->editor, $directory))->toThrow(RuntimeException::class, 'editorial changes');
});

it('rejects a bundle whose asset differs from its recorded checksum', function (): void {
    $source = app_path('Modules/SiteReadysetgo/Database/Seeders/bundle');
    $copy = Storage::disk('local')->path('tampered-bundle');
    mkdir($copy.'/assets', 0777, true);
    copy($source.'/bundle.json', $copy.'/bundle.json');
    foreach (glob($source.'/assets/*') as $file) {
        copy($file, $copy.'/assets/'.basename($file));
    }
    file_put_contents($copy.'/assets/logo.png', 'tampered');
    $tenant = bundleTenant($this->editor, 'readysetgo', 'readysetgoghana.com');

    expect(fn () => app(SiteBundleImporter::class)->import($tenant, $this->editor, $copy))->toThrow(RuntimeException::class, 'differs from the audited source');
    expect(Post::query()->count())->toBe(0);
});

it('requires existing editors and a known site module for the import command', function (): void {
    $this->artisan('cms:import-site-bundle', ['module' => 'SiteKunneo'])->assertSuccessful();
    $this->artisan('cms:import-site-bundle', ['module' => 'SiteKunneo', '--execute' => true, '--editor' => ['nobody@example.test']])->assertFailed();
    $this->artisan('cms:import-site-bundle', ['module' => 'SiteMissing'])->assertFailed();
});

it('serves the imported site on its custom domain with slider, menu and legacy page URLs', function (string $module, string $slug, string $domain, string $siteName, int $slides): void {
    $tenant = bundleTenant($this->editor, $slug, $domain);
    foreach (['cms-core', 'site-'.$slug] as $moduleSlug) {
        App\Models\TenantModule::query()->create(['tenant_id' => $tenant->id, 'module_slug' => $moduleSlug, 'is_enabled' => true, 'version' => '1.0.0']);
    }
    app(SiteBundleImporter::class)->import($tenant, $this->editor, app_path('Modules/'.$module.'/Database/Seeders/bundle'));

    $home = $this->get('https://'.$domain.'/')->assertOk()->assertSee($siteName)->assertSee('/aboutus', false)->assertSee('/contactus', false);
    expect(mb_substr_count($home->getContent(), 'aria-roledescription="carousel"'))->toBe($slides > 0 ? 1 : 0)
        ->and(mb_substr_count($home->getContent(), 'aria-label="Show slide'))->toBe($slides > 1 ? $slides : 0);
    $this->get('https://'.$domain.'/aboutus')->assertOk()->assertSee('Who We Are');
    $this->get('https://'.$domain.'/contactus')->assertOk();
    $this->get('https://'.$domain.'/sitemap.xml')->assertOk();
})->with([
    'kunneo' => ['SiteKunneo', 'kunneo', 'kunneo.com', 'Kunneo Creations', 4],
    'readysetgo' => ['SiteReadysetgo', 'readysetgo', 'readysetgoghana.com', 'Ghana Sports Movement', 0],
]);
