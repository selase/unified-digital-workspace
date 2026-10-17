<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\CmsCore\Models\Menu;
use App\Modules\CmsCore\Models\MenuItem;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostType;
use App\Modules\CmsCore\Support\BlockRendererService;

function createCmsPageBuilderContext(): array
{
    $user = User::factory()->create([
        'email' => 'cms-editor-'.uniqid().'@example.com',
    ]);
    $tenant = setActiveTenantForTest($user);

    foreach (['cms.posts.view', 'cms.posts.create', 'cms.posts.update', 'cms.posts.delete', 'cms.menus.view', 'cms.menus.manage'] as $name) {
        Spatie\Permission\Models\Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Illuminate\Support\Str::uuid(), 'category' => 'cms']);
    }

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo(['cms.posts.view', 'cms.posts.create', 'cms.posts.update', 'cms.posts.delete', 'cms.menus.view', 'cms.menus.manage']);
    app(App\Services\ModuleManager::class)->enableForTenant('cms-core', $tenant);

    $postType = PostType::firstOrCreate([
        'tenant_id' => $tenant->id,
        'slug' => 'post',
    ], [
        'name' => 'Article',
    ]);

    $menu = Menu::firstOrCreate([
        'tenant_id' => $tenant->id,
        'name' => 'Main Institutional Navigation',
    ], [
        'slug' => 'main-nav',
    ]);

    $parentMenuItem = MenuItem::firstOrCreate([
        'tenant_id' => $tenant->id,
        'menu_id' => $menu->id,
        'label' => 'About Us',
    ], [
        'url' => '/about',
        'sort_order' => 1,
    ]);

    return [$user, $tenant, $postType, $menu, $parentMenuItem];
}

describe('Phase 23: Visual Block Page Builder & Inline Menu Attachment (TRK-CMS-PAGEBUILDER)', function (): void {

    test('sanitizes malicious script and event handler injection in visual blocks', function (): void {
        $maliciousBlocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Secure Landing <script>alert("xss")</script>',
                    'subtitle' => 'Safe narrative <img src=x onerror="alert(1)">',
                    'cta_label' => 'Click Here<svg onload=alert(2)>',
                    'cta_url' => 'javascript:alert(3)',
                ],
            ],
            [
                'type' => 'rich_text',
                'data' => [
                    'content' => '<p>Allowed text</p><script>document.cookie="stolen"</script><iframe src="malicious.org"></iframe>',
                ],
            ],
            [
                'type' => 'cta_banner',
                'data' => [
                    'headline' => 'Take Action <script>evil()</script>',
                    'description' => 'Safe description',
                    'button_text' => 'Join Now',
                    'button_url' => '/site/join',
                ],
            ],
        ];

        $sanitized = BlockRendererService::sanitizeBlocks($maliciousBlocks);
        $rendered = BlockRendererService::render($sanitized);

        // Assert that executable script tags, event handlers, and javascript: protocols are stripped
        expect($rendered)->not->toContain('<script>')
            ->not->toContain('alert(')
            ->not->toContain('onerror=')
            ->not->toContain('onload=')
            ->not->toContain('javascript:alert')
            ->not->toContain('<iframe')
            ->toContain('Secure Landing')
            ->toContain('Safe narrative')
            ->toContain('Allowed text')
            ->toContain('Take Action')
            ->toContain('/site/join');
    });

    test('renders comprehensive visual blocks including hero, stats, features, faq, and cta banner', function (): void {
        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Master Planned Eco-City',
                    'subtitle' => 'Fully registered, titled land parcels in prime expansion corridors.',
                    'cta_label' => 'Explore Parcels',
                    'cta_url' => '/site/properties',
                ],
            ],
            [
                'type' => 'stats_grid',
                'data' => [
                    'items' => [
                        ['label' => 'Acres Secured', 'value' => '1,500', 'suffix' => '+'],
                        ['label' => 'Verified Clients', 'value' => '850', 'suffix' => ''],
                    ],
                ],
            ],
            [
                'type' => 'feature_cards',
                'data' => [
                    'items' => [
                        ['title' => 'Indenture Registry', 'description' => 'All deeds certified by Ghana Lands Commission.'],
                    ],
                ],
            ],
            [
                'type' => 'faq_accordion',
                'data' => [
                    'items' => [
                        ['question' => 'How are installment payments handled?', 'answer' => '<p>Through automated double-entry escrow tracking.</p>'],
                    ],
                ],
            ],
            [
                'type' => 'cta_banner',
                'data' => [
                    'headline' => 'Reserve Your Plot Today',
                    'description' => 'Dedicated relationship managers standing by.',
                    'button_text' => 'Speak to an Expert',
                    'button_url' => '/site/contact',
                ],
            ],
        ];

        $rendered = BlockRendererService::render($blocks);

        expect($rendered)
            ->toContain('Master Planned Eco-City')
            ->toContain('Acres Secured')
            ->toContain('1,500')
            ->toContain('+')
            ->toContain('Indenture Registry')
            ->toContain('How are installment payments handled?')
            ->toContain('Reserve Your Plot Today')
            ->toContain('Speak to an Expert');
    });

    test('creates and updates a post with inline menu and sub-menu placement', function (): void {
        [$user, $tenant, $postType, $menu, $parentMenuItem] = createCmsPageBuilderContext();

        // 1. Create Post and attach as sub-menu under "About Us"
        $createResponse = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('cms-core.posts.store'), [
                'post_type_id' => $postType->id,
                'title' => 'Executive Leadership Team',
                'slug' => 'executive-leadership-team',
                'status' => 'published',
                'body' => '<p>Overview of the institutional governance and leadership council.</p>',
                'author_id' => (string) $user->uuid,
                'attach_to_menu_id' => $menu->id,
                'parent_menu_item_id' => $parentMenuItem->id,
                'menu_item_label' => 'Leadership Council',
                'blocks_json' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Board of Directors & Executives',
                            'subtitle' => 'Institutional leadership guiding sustainable growth.',
                            'cta_label' => 'View Structure',
                            'cta_url' => '/site/leadership',
                        ],
                    ],
                ],
            ]);

        $createResponse->assertRedirect();

        $post = Post::where('slug', 'executive-leadership-team')->firstOrFail();
        expect($post->hasBlocks())->toBeTrue();
        expect($post->blocks_json[0]['type'])->toBe('hero');

        // Verify MenuItem was created and nested under parentMenuItem
        $menuItem = MenuItem::where('post_id', $post->id)->firstOrFail();
        expect($menuItem->menu_id)->toBe($menu->id)
            ->and($menuItem->parent_id)->toBe($parentMenuItem->id)
            ->and($menuItem->label)->toBe('Leadership Council')
            ->and($menuItem->url)->toBe('/site/news/executive-leadership-team');

        // 2. View public show page and assert visual block content renders
        $publicShowResponse = $this->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('cms-core.public.posts.show', $post->slug));

        $publicShowResponse->assertOk()
            ->assertSee('Board of Directors & Executives')
            ->assertSee('Institutional leadership guiding sustainable growth.');

        // 3. Update Post: move to top-level menu item with new label
        $updateResponse = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->put(route('cms-core.posts.update', $post), [
                'post_type_id' => $postType->id,
                'title' => 'Executive Leadership Team',
                'slug' => 'executive-leadership-team',
                'status' => 'published',
                'body' => '<p>Updated leadership council details.</p>',
                'author_id' => (string) $user->uuid,
                'attach_to_menu_id' => $menu->id,
                'parent_menu_item_id' => null, // Promoted to top-level
                'menu_item_label' => 'Our Leaders',
            ]);

        $updateResponse->assertRedirect();

        $menuItem->refresh();
        expect($menuItem->parent_id)->toBeNull()
            ->and($menuItem->label)->toBe('Our Leaders');
    });

    test('rejects cross-tenant menu attachment or non-existent menu IDs', function (): void {
        [$user, $tenant, $postType, $menu, $parentMenuItem] = createCmsPageBuilderContext();

        // Foreign Tenant Menu
        $foreignTenant = Tenant::create(['name' => 'Foreign Corp', 'slug' => 'foreign-'.uniqid()]);
        $foreignMenu = Menu::create([
            'tenant_id' => $foreignTenant->id,
            'name' => 'Foreign Menu',
            'slug' => 'foreign-menu',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('cms-core.posts.store'), [
                'post_type_id' => $postType->id,
                'title' => 'Attempted Cross-Tenant Page',
                'status' => 'published',
                'body' => '<p>Malicious cross-tenant attempt.</p>',
                'author_id' => (string) $user->uuid,
                'attach_to_menu_id' => $foreignMenu->id, // Belongs to foreign tenant!
            ]);

        $response->assertSessionHasErrors(['attach_to_menu_id']);
        expect(Post::where('title', 'Attempted Cross-Tenant Page')->exists())->toBeFalse();
    });
});
