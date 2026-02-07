<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\CmsCore\Database\Factories\CategoryFactory;
use App\Modules\CmsCore\Database\Factories\MediaFactory;
use App\Modules\CmsCore\Database\Factories\PostFactory;
use App\Modules\CmsCore\Database\Factories\PostTypeFactory;
use App\Modules\CmsCore\Database\Factories\TagFactory;
use App\Services\ModuleManager;

use function Pest\Laravel\actingAs;

/**
 * @return array{0: User, 1: Tenant}
 */
function createCmsApiContext(): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    app(ModuleManager::class)->enableForTenant('cms-core', $tenant);

    return [$user, $tenant];
}

it('returns post types', function () {
    [$user, $tenant] = createCmsApiContext();

    $postType = PostTypeFactory::new()->forTenant($tenant->id)->create();

    $response = actingAs($user, 'sanctum')
        ->getJson('/api/cms-core/v1/post-types');

    $response->assertSuccessful()
        ->assertJsonFragment([
            'id' => $postType->id,
            'uuid' => $postType->uuid,
            'slug' => $postType->slug,
        ]);
});

it('creates and updates post types', function () {
    [$user, $tenant] = createCmsApiContext();

    $createResponse = actingAs($user, 'sanctum')->postJson('/api/cms-core/v1/post-types', [
        'name' => 'News',
        'description' => 'Company news',
    ]);

    $createResponse->assertSuccessful();

    $postTypeId = $createResponse->json('data.id');
    $postTypeUuid = $createResponse->json('data.uuid');

    $updateResponse = actingAs($user, 'sanctum')->putJson("/api/cms-core/v1/post-types/{$postTypeUuid}", [
        'description' => 'Updated news',
    ]);

    $updateResponse->assertSuccessful()
        ->assertJsonFragment([
            'id' => $postTypeId,
            'description' => 'Updated news',
        ]);
});

it('returns posts with taxonomies and media', function () {
    [$user, $tenant] = createCmsApiContext();

    $post = PostFactory::new()->forTenant($tenant->id)->create();
    $category = CategoryFactory::new()->forTenant($tenant->id)->create();
    $tag = TagFactory::new()->forTenant($tenant->id)->create();
    $media = MediaFactory::new()->forTenant($tenant->id)->create();

    $post->categories()->attach($category->id, ['sort_order' => 0]);
    $post->tags()->attach($tag->id);
    $post->media()->attach($media->id, ['role' => 'attachment', 'sort_order' => 1]);

    $response = actingAs($user, 'sanctum')
        ->getJson('/api/cms-core/v1/posts');

    $response->assertSuccessful()
        ->assertJsonFragment([
            'id' => $post->id,
            'uuid' => $post->uuid,
            'title' => $post->title,
        ]);
});

it('creates and updates posts', function () {
    [$user, $tenant] = createCmsApiContext();

    $postType = PostTypeFactory::new()->forTenant($tenant->id)->create();
    $category = CategoryFactory::new()->forTenant($tenant->id)->create();
    $tag = TagFactory::new()->forTenant($tenant->id)->create();

    $createResponse = actingAs($user, 'sanctum')->postJson('/api/cms-core/v1/posts', [
        'post_type_id' => $postType->id,
        'title' => 'Hello CMS',
        'status' => 'draft',
        'body' => 'Content',
        'author_id' => $user->id,
        'category_ids' => [$category->id],
        'tag_ids' => [$tag->id],
    ]);

    $createResponse->assertSuccessful();

    $postUuid = $createResponse->json('data.uuid');

    $updateResponse = actingAs($user, 'sanctum')->putJson("/api/cms-core/v1/posts/{$postUuid}", [
        'status' => 'published',
        'published_at' => now()->toISOString(),
    ]);

    $updateResponse->assertSuccessful()
        ->assertJsonFragment([
            'uuid' => $postUuid,
            'status' => 'published',
        ]);
});

it('deletes posts', function () {
    [$user, $tenant] = createCmsApiContext();

    $post = PostFactory::new()->forTenant($tenant->id)->create([
        'author_id' => $user->id,
    ]);

    $response = actingAs($user, 'sanctum')
        ->deleteJson("/api/cms-core/v1/posts/{$post->uuid}");

    $response->assertNoContent();
});

it('returns media library', function () {
    [$user, $tenant] = createCmsApiContext();

    $media = MediaFactory::new()->forTenant($tenant->id)->create();

    $response = actingAs($user, 'sanctum')
        ->getJson('/api/cms-core/v1/media');

    $response->assertSuccessful()
        ->assertJsonFragment([
            'id' => $media->id,
            'uuid' => $media->uuid,
            'filename' => $media->filename,
        ]);
});

it('creates and updates media records', function () {
    [$user, $tenant] = createCmsApiContext();

    $post = PostFactory::new()->forTenant($tenant->id)->create([
        'author_id' => $user->id,
    ]);

    $createResponse = actingAs($user, 'sanctum')->postJson('/api/cms-core/v1/media', [
        'disk' => 'public',
        'path' => 'media/example.jpg',
        'original_filename' => 'example.jpg',
        'filename' => 'example.jpg',
        'extension' => 'jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 2048,
        'uploaded_by' => $user->id,
        'post_ids' => [$post->id],
    ]);

    $createResponse->assertSuccessful();

    $mediaUuid = $createResponse->json('data.uuid');

    $updateResponse = actingAs($user, 'sanctum')->putJson("/api/cms-core/v1/media/{$mediaUuid}", [
        'title' => 'Cover Image',
        'is_public' => false,
    ]);

    $updateResponse->assertSuccessful()
        ->assertJsonFragment([
            'uuid' => $mediaUuid,
            'title' => 'Cover Image',
            'is_public' => false,
        ]);
});

it('deletes media records', function () {
    [$user, $tenant] = createCmsApiContext();

    $media = MediaFactory::new()->forTenant($tenant->id)->create([
        'uploaded_by' => $user->id,
    ]);

    $response = actingAs($user, 'sanctum')
        ->deleteJson("/api/cms-core/v1/media/{$media->uuid}");

    $response->assertNoContent();
});

it('creates and updates menus', function () {
    [$user, $tenant] = createCmsApiContext();

    $createResponse = actingAs($user, 'sanctum')->postJson('/api/cms-core/v1/menus', [
        'name' => 'Primary',
    ]);

    $createResponse->assertSuccessful();

    $menuUuid = $createResponse->json('data.uuid');

    $updateResponse = actingAs($user, 'sanctum')->putJson("/api/cms-core/v1/menus/{$menuUuid}", [
        'name' => 'Main',
    ]);

    $updateResponse->assertSuccessful()
        ->assertJsonFragment([
            'uuid' => $menuUuid,
            'name' => 'Main',
        ]);
});

it('creates and updates settings', function () {
    [$user, $tenant] = createCmsApiContext();

    $createResponse = actingAs($user, 'sanctum')->postJson('/api/cms-core/v1/settings', [
        'group' => 'general',
        'key' => 'site_name',
        'value' => 'UDW',
    ]);

    $createResponse->assertSuccessful();

    $settingUuid = $createResponse->json('data.uuid');

    $updateResponse = actingAs($user, 'sanctum')->putJson("/api/cms-core/v1/settings/{$settingUuid}", [
        'value' => 'UDW Platform',
    ]);

    $updateResponse->assertSuccessful()
        ->assertJsonFragment([
            'uuid' => $settingUuid,
            'value' => 'UDW Platform',
        ]);
});
