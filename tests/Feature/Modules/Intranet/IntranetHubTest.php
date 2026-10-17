<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostType;
use App\Modules\Intranet\Models\IntranetBoardPost;
use App\Modules\Intranet\Models\IntranetEvent;
use App\Modules\Intranet\Models\IntranetMessageBoard;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: User, 1: Tenant}
 */
function createIntranetTestContext(): array
{
    $user = User::factory()->create([
        'first_name' => 'Intranet',
        'last_name' => 'Officer',
    ]);
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = [
        'intranet.view',
        'intranet.manage',
        'intranet.events.manage',
        'intranet.boards.post',
        'cms.posts.view',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ], [
            'category' => 'intranet',
            'uuid' => (string) Str::uuid(),
        ]);
    }

    $user->givePermissionTo($permissions);

    app(TenantDatabaseManager::class)->configure($tenant);
    app(ModuleManager::class)->enableForTenant('intranet', $tenant);
    app(ModuleManager::class)->enableForTenant('cms-core', $tenant);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/CmsCore/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/Intranet/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

    return [$user, $tenant];
}

describe('Phase 22: Institutional Intranet Portal & Internal-Only CMS Content Scoping', function (): void {
    test('enforces strict CMS audience scoping where internal posts are hidden from public website and visible only in intranet', function (): void {
        [$user, $tenant] = createIntranetTestContext();

        $postType = PostType::firstOrCreate([
            'slug' => 'post',
        ], [
            'name' => 'Post',
        ]);

        // 1. Create Public Post
        $publicPost = Post::create([
            'post_type_id' => $postType->id,
            'title' => 'Public Press Release: Expansion into Western Region',
            'slug' => 'public-press-release-expansion',
            'status' => 'published',
            'audience' => 'public',
            'published_at' => now()->subDay(),
            'author_id' => (string) $user->uuid,
            'body' => '<p>Public press release content.</p>',
        ]);

        // 2. Create Internal-Only Post (Leadership Directive)
        $internalPost = Post::create([
            'post_type_id' => $postType->id,
            'title' => 'Executive Directive: Confidential Q4 Cost Optimization Strategy',
            'slug' => 'confidential-q4-cost-optimization-strategy',
            'status' => 'published',
            'audience' => 'internal_only',
            'published_at' => now()->subHours(2),
            'author_id' => (string) $user->uuid,
            'body' => '<p>Internal confidential strategy content strictly for institutional staff.</p>',
        ]);

        // 3. Public News Archive only displays the public post
        $publicArchive = $this->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('cms-core.public.posts.archive'));

        $publicArchive->assertOk()
            ->assertSee('Public Press Release: Expansion into Western Region')
            ->assertDontSee('Confidential Q4 Cost Optimization Strategy');

        // 4. Public Show for internal post strictly returns 404
        $this->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('cms-core.public.posts.show', $internalPost->slug))
            ->assertNotFound();

        // 5. Authenticated Intranet Hub displays the internal executive directive
        $intranetHub = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('intranet.index'));

        $intranetHub->assertOk()
            ->assertSee('Confidential Q4 Cost Optimization Strategy')
            ->assertSee('internal_only');
    });

    test('protects internal media assets with authentication check and private cache headers', function (): void {
        Storage::fake('public');
        [$user, $tenant] = createIntranetTestContext();

        $fakeFile = UploadedFile::fake()->create('confidential_salary_review.pdf', 500, 'application/pdf');
        $storedPath = $fakeFile->store("tenants/{$tenant->id}/cms", 'public');

        $internalMedia = Media::create([
            'tenant_id' => $tenant->id,
            'filename' => 'confidential_salary_review.pdf',
            'original_filename' => 'confidential_salary_review.pdf',
            'path' => $storedPath,
            'disk' => 'public',
            'mime_type' => 'application/pdf',
            'size_bytes' => 500,
            'uploaded_by' => (string) $user->uuid,
            'is_internal' => true,
        ]);

        // Unauthenticated guest request to internal media MUST be rejected (403)
        $this->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('cms-core.media.serve', $internalMedia))
            ->assertForbidden();

        // Authenticated staff user can stream file with private no-store headers
        $authResponse = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('cms-core.media.serve', $internalMedia));

        $authResponse->assertOk();
        expect((string) $authResponse->headers->get('Cache-Control'))
            ->toContain('private')
            ->toContain('no-store');
    });

    test('manages upcoming institutional events and interactive message board posts', function (): void {
        [$user, $tenant] = createIntranetTestContext();

        // 1. Schedule Event
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('intranet.events.store'), [
                'title' => 'Annual General Staff Assembly & Town Hall',
                'event_date' => now()->addDays(5)->toDateString(),
                'start_time' => '10:00',
                'end_time' => '12:30',
                'location' => 'Main Executive Auditorium',
                'category' => 'all_hands',
                'description' => 'Review of Q3 institutional milestones and strategic roadmap.',
            ])
            ->assertRedirect(route('intranet.index'));

        $event = IntranetEvent::query()->where('title', 'Annual General Staff Assembly & Town Hall')->firstOrFail();
        expect($event->category)->toBe('all_hands');

        // 2. Post to Message Board
        $board = IntranetMessageBoard::create([
            'title' => 'General Staff Community Noticeboard',
            'slug' => 'general-staff-community-noticeboard',
            'description' => 'Open announcements and staff collaboration items.',
            'is_pinned' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('intranet.boards.posts.store', $board), [
                'title' => 'Voluntary Blood Donation Drive This Friday',
                'content' => 'Join the clinical team in the medical quadrangle between 9am and 2pm.',
                'is_announcement' => 1,
            ])
            ->assertRedirect(route('intranet.index'));

        $boardPost = IntranetBoardPost::query()->where('intranet_message_board_id', $board->id)->firstOrFail();
        expect($boardPost->is_announcement)->toBeTrue()
            ->and($boardPost->author_id)->toBe((string) $user->uuid);

        // 3. Verify Intranet Hub renders the event and message
        $hubResponse = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('intranet.index'));

        $hubResponse->assertOk()
            ->assertSee('Annual General Staff Assembly &amp; Town Hall', false)
            ->assertSee('Voluntary Blood Donation Drive This Friday');
    });

    test('adversarial negative tests enforce RBAC and cross-tenant isolation on intranet boards', function (): void {
        [$userA, $tenantA] = createIntranetTestContext();

        $otherTenant = Tenant::factory()->create(['isolation_mode' => 'shared']);
        $foreignBoard = IntranetMessageBoard::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'title' => 'Foreign Tenant Noticeboard',
            'slug' => 'foreign-tenant-noticeboard',
        ]);

        // Attempting to post to a foreign tenant board returns 404
        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->post(route('intranet.boards.posts.store', ['board' => $foreignBoard->slug]), [
                'title' => 'Attempted Cross-Tenant Message',
                'content' => 'Cross-tenant payload should be rejected.',
            ])
            ->assertNotFound();

        // Unpermitted user receives 403
        $unpermitted = User::factory()->create(['tenant_id' => $tenantA->id]);
        $unpermitted->tenants()->syncWithoutDetaching([$tenantA->id]);

        $this->actingAs($unpermitted)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->get(route('intranet.index'))
            ->assertForbidden();
    });
});
