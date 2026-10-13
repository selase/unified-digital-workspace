<?php

declare(strict_types=1);

use App\Libraries\ActivityLogPresenter;
use App\Models\User;
use App\Models\UserLoginHistory;
use App\Modules\CmsCore\Database\Factories\PostFactory;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostMeta;
use App\Services\ModuleManager;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

/**
 * @param  array<int, string>  $permissions
 * @return array{0: User, 1: App\Models\Tenant}
 */
function cmsEditorWith(array $permissions, array $tenantOverrides = []): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user, $tenantOverrides);
    $user->forceFill(['tenant_id' => $tenant->id])->save();

    foreach ($permissions as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Str::uuid(), 'category' => 'cms']);
    }

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo($permissions);

    app(ModuleManager::class)->enableForTenant('cms-core', $tenant);

    return [$user, $tenant];
}

it('shows an editor a content overview without developer links', function (): void {
    [$editor, $tenant] = cmsEditorWith(['cms.posts.view', 'cms.posts.create', 'cms.media.view']);

    $this->actingAs($editor)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/cms-core')
        ->assertSuccessful()
        ->assertSee('What would you like to do?')
        ->assertSee('Media library')
        ->assertDontSee('For developers')
        ->assertDontSee('Website settings')
        ->assertDontSee('Menus');
});

it('shows developer links on the hub to someone who manages the website', function (): void {
    [$manager, $tenant] = cmsEditorWith(['cms.posts.view', 'cms.settings.manage']);

    $this->actingAs($manager)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/cms-core')
        ->assertSuccessful()
        ->assertSee('For developers')
        ->assertSee('Website settings');
});

it('saves slide buttons and display order from the post form', function (): void {
    [$editor, $tenant] = cmsEditorWith(['cms.posts.view', 'cms.posts.update']);

    $post = PostFactory::new()->forTenant($tenant->id)->create(['author_id' => $editor->uuid, 'title' => 'Slide one', 'slug' => 'slide-one']);
    PostMeta::query()->create(['post_id' => $post->id, 'tenant_id' => $tenant->id, 'key' => 'cta2_label', 'value' => 'Old button']);

    $payload = [
        'title' => 'Slide one',
        'slug' => 'slide-one',
        'post_type_id' => $post->post_type_id,
        'status' => $post->status,
        'sort_order' => 3,
        'cta_label' => 'Learn more',
        'cta_url' => '/about',
        'cta2_label' => '',
        'cta2_url' => '',
    ];

    $this->actingAs($editor)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->put(route('cms-core.posts.update', $post), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $meta = PostMeta::query()->where('post_id', $post->id)->pluck('value', 'key');

    expect($post->fresh()->sort_order)->toBe(3)
        ->and($meta['cta_label'] ?? null)->toBe('Learn more')
        ->and($meta['cta_url'] ?? null)->toBe('/about')
        ->and($meta->has('cta2_label'))->toBeFalse();

    $this->actingAs($editor)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('cms-core.posts.edit', $post))
        ->assertSuccessful()
        ->assertSee('Buttons and Display Order')
        ->assertSee('value="Learn more"', false);
});

it('rejects a slide button with an unsafe or missing link', function (string $url): void {
    [$editor, $tenant] = cmsEditorWith(['cms.posts.view', 'cms.posts.update']);
    $post = PostFactory::new()->forTenant($tenant->id)->create(['author_id' => $editor->uuid]);

    $this->actingAs($editor)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->put(route('cms-core.posts.update', $post), [
            'title' => $post->title,
            'post_type_id' => $post->post_type_id,
            'status' => $post->status,
            'cta_label' => 'Click me',
            'cta_url' => $url,
        ])
        ->assertSessionHasErrors('cta_url');

    expect(PostMeta::query()->where('post_id', $post->id)->where('key', 'cta_label')->exists())->toBeFalse();
})->with([
    'script link' => 'javascript:alert(1)',
    'protocol-relative link' => '//evil.example',
    'missing link' => '',
]);

it('uses the organisation logo in the sidebar and never the template logo', function (): void {
    [$editor, $tenant] = cmsEditorWith(['cms.posts.view'], ['name' => 'Thyroid Ghana Foundation', 'logo' => 'logos/tenants/tgf.png']);

    $withLogo = $this->actingAs($editor)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/cms-core')
        ->assertSuccessful()
        ->getContent();

    expect($withLogo)
        ->toContain('logos/tenants/tgf.png')
        ->toContain('alt="Thyroid Ghana Foundation"')
        ->not->toContain('metronic/media/app/default-logo');

    $tenant->update(['logo' => null]);

    $withoutLogo = $this->actingAs($editor)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/cms-core')
        ->getContent();

    expect($withoutLogo)
        ->toContain('Thyroid Ghana Foundation')
        ->not->toContain('metronic/media/app/default-logo');
});

it('explains a refused page in plain language', function (): void {
    [$editor, $tenant] = cmsEditorWith(['cms.posts.view']);

    $this->actingAs($editor)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('cms-core.menus.index'))
        ->assertForbidden()
        ->assertSee('have access to this page')
        ->assertSee('Go to home page')
        ->assertDontSee('metronic/media/app/default-logo');
});

it('keeps a profile private to its owner and their user managers', function (): void {
    [$editor, $tenant] = cmsEditorWith(['cms.posts.view']);
    $colleague = User::factory()->create(['tenant_id' => $tenant->id]);
    $outsider = User::factory()->create();

    $this->actingAs($editor)->get(route('profile.index', $editor))->assertSuccessful();
    $this->actingAs($editor)->get(route('profile.index', $colleague))->assertForbidden();
    $this->actingAs($editor)->get(route('profile.index', $outsider))->assertForbidden();

    Permission::firstOrCreate(['name' => 'core.users.read', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid(), 'category' => 'core']);
    $editor->givePermissionTo('core.users.read');

    $this->actingAs($editor)->get(route('profile.index', $colleague))->assertSuccessful();
    $this->actingAs($editor)->get(route('profile.index', $outsider))->assertForbidden();
});

it('describes activity in plain words without exposing record contents', function (): void {
    $signIn = new Activity(['description' => 'This model has been created', 'event' => 'created', 'subject_type' => UserLoginHistory::class]);
    $edit = new Activity([
        'description' => 'This model has been updated',
        'event' => 'updated',
        'subject_type' => Post::class,
        'properties' => ['attributes' => ['title' => 'Secret draft', 'updated_at' => now()], 'old' => ['title' => 'Old']],
    ]);

    expect(ActivityLogPresenter::describe($signIn))->toBe(['summary' => 'Signed in', 'details' => null])
        ->and(ActivityLogPresenter::describe($edit))->toBe(['summary' => 'Updated a post', 'details' => 'Changed: title']);
});
