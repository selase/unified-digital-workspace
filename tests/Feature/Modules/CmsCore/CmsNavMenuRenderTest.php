<?php

declare(strict_types=1);

use App\Modules\CmsCore\Database\Factories\PostFactory;
use App\Modules\CmsCore\Database\Factories\PostTypeFactory;
use App\Modules\CmsCore\Models\Menu;
use App\Modules\CmsCore\Models\MenuItem;
use App\Services\ModuleManager;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();

    $this->tenant = setActiveTenantForTest(null, ['slug' => 'nav-site']);
    app(ModuleManager::class)->enableForTenant('cms-core', $this->tenant);
});

test('header nav renders menu items that have an explicit url and items linked to posts', function (): void {
    $tenantId = (string) $this->tenant->id;

    $pageType = PostTypeFactory::new()->forTenant($tenantId)->create(['slug' => 'page']);
    $about = PostFactory::new()->published()->forTenant($tenantId)->create([
        'post_type_id' => $pageType->id,
        'title' => 'About',
        'slug' => 'about',
    ]);

    $menu = Menu::query()->create(['tenant_id' => $tenantId, 'name' => 'Header', 'slug' => 'header']);
    MenuItem::query()->create([
        'tenant_id' => $tenantId,
        'menu_id' => $menu->id,
        'label' => 'Donate',
        'url' => 'https://example.org/donate',
        'sort_order' => 1,
    ]);
    MenuItem::query()->create([
        'tenant_id' => $tenantId,
        'menu_id' => $menu->id,
        'label' => 'About us',
        'post_id' => $about->id,
        'sort_order' => 2,
    ]);

    $html = view('cms-core::components.nav', [
        'menu' => $menu->load('items.post.postType'),
        'logo' => null,
        'siteName' => 'Nav Site',
    ])->render();

    expect($html)
        ->toContain('Donate')
        ->toContain('https://example.org/donate')
        ->toContain('About us')
        ->toContain('/about');
});
