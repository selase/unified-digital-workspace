<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CmsCore;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\CmsCore\Database\Factories\CategoryFactory;
use App\Modules\CmsCore\Database\Factories\PostFactory;
use App\Modules\CmsCore\Database\Factories\PostTypeFactory;
use App\Modules\CmsCore\Database\Factories\TagFactory;
use App\Modules\CmsCore\Models\Category;
use App\Modules\CmsCore\Models\Post;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @param  array<int, string>  $permissions
 * @return array{0: User, 1: Tenant}
 */
function createCmsWebContext(array $permissions = []): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);
    $user->forceFill(['tenant_id' => $tenant->id])->save();

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $defaultPermissions = [
        'cms.posts.view',
        'cms.posts.create',
        'cms.posts.update',
        'cms.posts.delete',
        'cms.categories.view',
        'cms.categories.manage',
        'cms.tags.view',
        'cms.media.view',
        'cms.settings.manage',
    ];

    $allPermissions = empty($permissions) ? $defaultPermissions : $permissions;

    foreach ($allPermissions as $name) {
        Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ], [
            'uuid' => (string) Str::uuid(),
            'category' => 'cms',
        ]);
    }

    $user->givePermissionTo($allPermissions);

    app(TenantDatabaseManager::class)->configure($tenant);
    app(ModuleManager::class)->enableForTenant('cms-core', $tenant);

    return [$user, $tenant];
}

it('renders the CMS hub with metrics and recent posts for authorized users', function (): void {
    [$user, $tenant] = createCmsWebContext();
    $postType = PostTypeFactory::new()->forTenant($tenant->id)->create(['name' => 'News Article']);

    $publishedPost = PostFactory::new()->forTenant($tenant->id)->create([
        'post_type_id' => $postType->id,
        'author_id' => $user->uuid,
        'title' => 'Breakthrough Clinical Trial Result',
        'slug' => 'breakthrough-clinical-trial-result',
        'status' => 'published',
    ]);

    $draftPost = PostFactory::new()->forTenant($tenant->id)->create([
        'post_type_id' => $postType->id,
        'author_id' => $user->uuid,
        'title' => 'Draft Institutional Safety Protocol',
        'slug' => 'draft-institutional-safety-protocol',
        'status' => 'draft',
    ]);

    $category = CategoryFactory::new()->forTenant($tenant->id)->create(['name' => 'Research News']);

    $response = $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('cms-core.index'));

    $response->assertSuccessful();
    $response->assertSee('CMS Hub');
    $response->assertSee('Total Content');
    $response->assertSee('Breakthrough Clinical Trial Result');
    $response->assertSee('Draft Institutional Safety Protocol');
    $response->assertSee('What would you like to do?');
    $response->assertSee('For developers');
});

it('blocks unauthorized access to the CMS hub', function (): void {
    [$user, $tenant] = createCmsWebContext(['cms.media.view']); // no cms.posts.view

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('cms-core.index'))
        ->assertForbidden();
});

it('lists posts with search and status filtering', function (): void {
    [$user, $tenant] = createCmsWebContext();
    $postType = PostTypeFactory::new()->forTenant($tenant->id)->create();

    PostFactory::new()->forTenant($tenant->id)->create([
        'post_type_id' => $postType->id,
        'author_id' => $user->uuid,
        'title' => 'Cardiac Surgery Guidelines 2026',
        'slug' => 'cardiac-surgery-guidelines-2026',
        'status' => 'published',
    ]);

    PostFactory::new()->forTenant($tenant->id)->create([
        'post_type_id' => $postType->id,
        'author_id' => $user->uuid,
        'title' => 'Pediatric Ward Renovation Update',
        'slug' => 'pediatric-ward-renovation-update',
        'status' => 'draft',
    ]);

    // Search filter
    $searchResponse = $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('cms-core.posts.index', ['search' => 'Cardiac']));

    $searchResponse->assertSuccessful();
    $searchResponse->assertSee('Cardiac Surgery Guidelines 2026');
    $searchResponse->assertDontSee('Pediatric Ward Renovation Update');

    // Status filter
    $statusResponse = $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('cms-core.posts.index', ['status' => 'draft']));

    $statusResponse->assertSuccessful();
    $statusResponse->assertSee('Pediatric Ward Renovation Update');
    $statusResponse->assertDontSee('Cardiac Surgery Guidelines 2026');
});

it('renders the create post form and stores a new post', function (): void {
    [$user, $tenant] = createCmsWebContext();
    $postType = PostTypeFactory::new()->forTenant($tenant->id)->create();
    $category = CategoryFactory::new()->forTenant($tenant->id)->create(['name' => 'Policies']);
    $tag = TagFactory::new()->forTenant($tenant->id)->create(['name' => 'compliance']);

    $createForm = $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('cms-core.posts.create'));

    $createForm->assertSuccessful();
    $createForm->assertSee('Create Post');

    $payload = [
        'author_id' => (string) $user->uuid,
        'title' => 'Institutional Infection Prevention Protocol',
        'slug' => 'institutional-infection-prevention-protocol',
        'post_type_id' => $postType->id,
        'status' => 'draft',
        'excerpt' => 'Mandatory hygiene and infection prevention measures for all hospital wards.',
        'body' => '<p>Standard sterilization operating procedures must be maintained around the clock.</p>',
        'category_ids' => [$category->id],
        'tag_ids' => [$tag->id],
    ];

    $storeResponse = $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('cms-core.posts.store'), $payload);

    $storeResponse->assertSessionHasNoErrors();
    $storeResponse->assertRedirect();

    $post = Post::query()->where('slug', 'institutional-infection-prevention-protocol')->first();
    expect($post)->not->toBeNull()
        ->and($post->title)->toBe('Institutional Infection Prevention Protocol')
        ->and($post->status)->toBe('draft')
        ->and((string) $post->author_id)->toBe((string) $user->uuid)
        ->and($post->categories->pluck('id')->all())->toContain($category->id)
        ->and($post->tags->pluck('id')->all())->toContain($tag->id);
});

it('renders post details with author, categories, and tags', function (): void {
    [$user, $tenant] = createCmsWebContext();
    $postType = PostTypeFactory::new()->forTenant($tenant->id)->create(['name' => 'Announcement']);
    $category = CategoryFactory::new()->forTenant($tenant->id)->create(['name' => 'Clinical Governance']);

    $post = PostFactory::new()->forTenant($tenant->id)->create([
        'post_type_id' => $postType->id,
        'author_id' => $user->uuid,
        'title' => 'Annual Quality Accreditation Results',
        'slug' => 'annual-quality-accreditation-results',
        'excerpt' => 'Hospital achieves top-tier accreditation score.',
        'body' => '<p>The accreditation committee completed comprehensive review with zero non-compliances.</p>',
        'status' => 'draft',
    ]);
    $post->categories()->attach($category->id);

    $response = $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('cms-core.posts.show', $post));

    $response->assertSuccessful();
    $response->assertSee('Annual Quality Accreditation Results');
    $response->assertSee('Hospital achieves top-tier accreditation score');
    $response->assertSee('Clinical Governance');
    $response->assertSee('Announcement');
    $response->assertSee('Publish Now');
});

it('allows updating an existing post', function (): void {
    [$user, $tenant] = createCmsWebContext();
    $postType = PostTypeFactory::new()->forTenant($tenant->id)->create();
    $post = PostFactory::new()->forTenant($tenant->id)->create([
        'post_type_id' => $postType->id,
        'author_id' => $user->uuid,
        'title' => 'Initial Title Draft',
        'slug' => 'initial-title-draft',
        'status' => 'draft',
        'body' => '<p>Original body text</p>',
    ]);

    $editResponse = $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('cms-core.posts.edit', $post));

    $editResponse->assertSuccessful();
    $editResponse->assertSee('Edit Post');

    $updatePayload = [
        'title' => 'Revised Title Finalized',
        'slug' => 'revised-title-finalized',
        'post_type_id' => $postType->id,
        'status' => 'draft',
        'body' => '<p>Updated revision body text</p>',
    ];

    $updateResponse = $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->put(route('cms-core.posts.update', $post), $updatePayload);

    $updateResponse->assertRedirect(route('cms-core.posts.show', $post));

    $post->refresh();
    expect($post->title)->toBe('Revised Title Finalized')
        ->and($post->slug)->toBe('revised-title-finalized');
});

it('allows quick publication and archival of posts', function (): void {
    [$user, $tenant] = createCmsWebContext();
    $postType = PostTypeFactory::new()->forTenant($tenant->id)->create();
    $post = PostFactory::new()->forTenant($tenant->id)->create([
        'post_type_id' => $postType->id,
        'author_id' => $user->uuid,
        'title' => 'Workflow Lifecycle Article',
        'slug' => 'workflow-lifecycle-article',
        'status' => 'draft',
    ]);

    // Quick Publish
    $publishResponse = $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('cms-core.posts.publish', $post));

    $publishResponse->assertRedirect(route('cms-core.posts.show', $post));

    $post->refresh();
    expect($post->status)->toBe('published')
        ->and($post->published_at)->not->toBeNull()
        ->and((string) $post->editor_id)->toBe((string) $user->uuid);

    // Quick Archive
    $archiveResponse = $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('cms-core.posts.archive', $post));

    $archiveResponse->assertRedirect(route('cms-core.posts.show', $post));

    $post->refresh();
    expect($post->status)->toBe('archived')
        ->and((string) $post->editor_id)->toBe((string) $user->uuid);
});

it('allows deleting a post', function (): void {
    [$user, $tenant] = createCmsWebContext();
    $postType = PostTypeFactory::new()->forTenant($tenant->id)->create();
    $post = PostFactory::new()->forTenant($tenant->id)->create([
        'post_type_id' => $postType->id,
        'author_id' => $user->uuid,
        'title' => 'Post to be removed',
        'slug' => 'post-to-be-removed',
    ]);

    $deleteResponse = $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->delete(route('cms-core.posts.destroy', $post));

    $deleteResponse->assertRedirect(route('cms-core.posts.index'));
    expect(Post::query()->find($post->id))->toBeNull();
});

it('manages categories lifecycle including create, update, and delete', function (): void {
    [$user, $tenant] = createCmsWebContext();

    // Index
    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('cms-core.categories.index'))
        ->assertSuccessful()
        ->assertSee('Categories');

    // Create
    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('cms-core.categories.create'))
        ->assertSuccessful()
        ->assertSee('New Category');

    $storeResponse = $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('cms-core.categories.store'), [
            'name' => 'Diagnostic Radiology Updates',
            'slug' => 'diagnostic-radiology-updates',
            'description' => 'News and protocol changes in diagnostic imaging.',
            'sort_order' => 1,
            'is_active' => '1',
        ]);

    $storeResponse->assertRedirect(route('cms-core.categories.index'));

    $category = Category::query()->where('slug', 'diagnostic-radiology-updates')->first();
    expect($category)->not->toBeNull()
        ->and($category->name)->toBe('Diagnostic Radiology Updates')
        ->and($category->is_active)->toBeTrue();

    // Edit & Update
    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('cms-core.categories.edit', $category))
        ->assertSuccessful()
        ->assertSee('Edit Category');

    $updateResponse = $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->put(route('cms-core.categories.update', $category), [
            'name' => 'Diagnostic Imaging & Radiology',
            'slug' => 'diagnostic-imaging-radiology',
            'description' => 'Updated scope',
            'sort_order' => 2,
            'is_active' => '1',
        ]);

    $updateResponse->assertRedirect(route('cms-core.categories.index'));

    $category->refresh();
    expect($category->name)->toBe('Diagnostic Imaging & Radiology')
        ->and($category->slug)->toBe('diagnostic-imaging-radiology');

    // Delete
    $deleteResponse = $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->delete(route('cms-core.categories.destroy', $category));

    $deleteResponse->assertRedirect(route('cms-core.categories.index'));
    expect(Category::query()->find($category->id))->toBeNull();
});

it('enforces permission gates on category management', function (): void {
    // User with view only
    [$user, $tenant] = createCmsWebContext(['cms.posts.view', 'cms.categories.view']);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('cms-core.categories.create'))
        ->assertForbidden();

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('cms-core.categories.store'), [
            'name' => 'Unauthorized Category',
        ])
        ->assertForbidden();
});
