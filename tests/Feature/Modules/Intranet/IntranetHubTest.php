<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostType;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\Intranet\Database\Seeders\IntranetSeeder;
use App\Modules\Intranet\Models\IntranetBoardPost;
use App\Modules\Intranet\Models\IntranetEvent;
use App\Modules\Intranet\Models\IntranetEventRsvp;
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
        'intranet.events.rsvp',
        'intranet.boards.manage',
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
    app(ModuleManager::class)->enableForTenant('hrms-core', $tenant);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/HrmsCore/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

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

    test('boards CRUD with audience scoping and strict protection against users whose department is null', function (): void {
        [$adminUser, $tenant] = createIntranetTestContext();

        // 1. Create a Department for testing
        $clinicalDept = Department::create([
            'name' => 'Clinical Services',
            'slug' => 'clinical-services-test',
            'is_active' => true,
        ]);

        // 2. Admin creates boards across audiences
        $this->actingAs($adminUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('intranet.boards.store'), [
                'title' => 'General Staff Noticeboard',
                'description' => 'General notices for all personnel',
                'audience' => IntranetMessageBoard::AUDIENCE_INTERNAL,
                'is_pinned' => 1,
            ])
            ->assertRedirect();

        $this->actingAs($adminUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('intranet.boards.store'), [
                'title' => 'Clinical Team Confidential Board',
                'description' => 'Restricted to clinical department',
                'audience' => IntranetMessageBoard::AUDIENCE_DEPARTMENTAL,
                'department_id' => $clinicalDept->id,
            ])
            ->assertRedirect();

        $internalBoard = IntranetMessageBoard::query()->where('title', 'General Staff Noticeboard')->firstOrFail();
        $deptBoard = IntranetMessageBoard::query()->where('title', 'Clinical Team Confidential Board')->firstOrFail();

        expect($internalBoard->audience)->toBe('internal')
            ->and($deptBoard->audience)->toBe('departmental')
            ->and($deptBoard->department_id)->toBe($clinicalDept->id);

        // 3. User with matching department can view the departmental board
        $doctorUser = User::factory()->create(['first_name' => 'Dr.', 'last_name' => 'Mensa']);
        $tenant->users()->attach($doctorUser->id);
        setPermissionsTeamId($tenant->id);
        $doctorUser->givePermissionTo(['intranet.view', 'intranet.boards.post']);

        $doctorEmployee = Employee::create([
            'user_id' => $doctorUser->id,
            'first_name' => 'Dr.',
            'last_name' => 'Mensa',
            'email' => $doctorUser->email,
            'mobile' => '+233201112233',
            'employee_staff_id' => 'EMP-DOC-001',
            'is_active' => true,
        ]);
        $doctorEmployee->departments()->attach($clinicalDept->id);

        $docShowResponse = $this->actingAs($doctorUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('intranet.boards.show', $deptBoard));

        $docShowResponse->assertOk()
            ->assertSee('Clinical Team Confidential Board');

        // 4. Adversarial Test: User whose department is NULL is strictly denied (403) and filtered out from list
        $nullDeptUser = User::factory()->create(['first_name' => 'Guest', 'last_name' => 'Staff']);
        $tenant->users()->attach($nullDeptUser->id);
        setPermissionsTeamId($tenant->id);
        $nullDeptUser->givePermissionTo(['intranet.view', 'intranet.boards.post']);

        // Assert query scoping hides departmental board from null-department user's index list
        $indexResponse = $this->actingAs($nullDeptUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('intranet.index'));

        $indexResponse->assertOk()
            ->assertSee('General Staff Noticeboard')
            ->assertDontSee('Clinical Team Confidential Board');

        // Direct show request returns 403
        $this->actingAs($nullDeptUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('intranet.boards.show', $deptBoard))
            ->assertForbidden();

        // Direct post request returns 403
        $this->actingAs($nullDeptUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('intranet.boards.posts.store', $deptBoard), [
                'title' => 'Intruder message',
                'content' => 'Should be rejected',
            ])
            ->assertForbidden();

        // 5. Board Edit & Delete by Admin
        $this->actingAs($adminUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->put(route('intranet.boards.update', $internalBoard), [
                'title' => 'Updated Staff Noticeboard',
                'description' => 'Updated description',
                'audience' => IntranetMessageBoard::AUDIENCE_INTERNAL,
                'is_pinned' => 0,
            ])
            ->assertRedirect(route('intranet.boards.show', $internalBoard));

        $internalBoard->refresh();
        expect($internalBoard->title)->toBe('Updated Staff Noticeboard')
            ->and($internalBoard->is_pinned)->toBeFalse();

        // Delete board
        $this->actingAs($adminUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->delete(route('intranet.boards.destroy', $internalBoard))
            ->assertRedirect(route('intranet.index'));

        expect(IntranetMessageBoard::query()->where('id', $internalBoard->id)->exists())->toBeFalse();
    });

    test('manages board posts with edit, delete, pin and nested replies', function (): void {
        [$adminUser, $tenant] = createIntranetTestContext();

        $board = IntranetMessageBoard::create([
            'title' => 'Engineering & Facilities Board',
            'slug' => 'engineering-facilities-board',
            'audience' => IntranetMessageBoard::AUDIENCE_INTERNAL,
            'is_pinned' => false,
        ]);

        $regularUser = User::factory()->create(['first_name' => 'Kojo', 'last_name' => 'Engineer']);
        $tenant->users()->attach($regularUser->id);
        setPermissionsTeamId($tenant->id);
        $regularUser->givePermissionTo(['intranet.view', 'intranet.boards.post']);

        // 1. Regular user creates a post
        $this->actingAs($regularUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('intranet.boards.posts.store', $board), [
                'title' => 'Scheduled Generator Servicing This Weekend',
                'content' => 'Standby generator set A will undergo routine filter replacement on Saturday.',
                'is_announcement' => 1,
            ])
            ->assertRedirect();

        $post = IntranetBoardPost::query()->where('title', 'Scheduled Generator Servicing This Weekend')->firstOrFail();
        expect($post->author_id)->toBe((string) $regularUser->uuid)
            ->and($post->is_pinned)->toBeFalse();

        // 2. Admin pins the post
        $this->actingAs($adminUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('intranet.posts.pin', $post))
            ->assertRedirect();

        $post->refresh();
        expect($post->is_pinned)->toBeTrue();

        // 3. Rival user cannot edit or delete the author's post (403)
        $rivalUser = User::factory()->create(['first_name' => 'Kwame', 'last_name' => 'Rival']);
        $tenant->users()->attach($rivalUser->id);
        setPermissionsTeamId($tenant->id);
        $rivalUser->givePermissionTo(['intranet.view', 'intranet.boards.post']);

        $this->actingAs($rivalUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->put(route('intranet.posts.update', $post), [
                'title' => 'Malicious edit',
                'content' => 'Defacing post',
            ])
            ->assertForbidden();

        $this->actingAs($rivalUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->delete(route('intranet.posts.destroy', $post))
            ->assertForbidden();

        // 4. Author can edit their own post
        $this->actingAs($regularUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->put(route('intranet.posts.update', $post), [
                'title' => 'Scheduled Generator Servicing This Sunday',
                'content' => 'Rescheduled to Sunday 8am.',
                'is_announcement' => 1,
            ])
            ->assertRedirect();

        $post->refresh();
        expect($post->title)->toBe('Scheduled Generator Servicing This Sunday');

        // 5. Other user posts a reply
        $this->actingAs($rivalUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('intranet.posts.replies.store', $post), [
                'content' => 'Noted. Will ensure laboratory cold chain backups are checked.',
            ])
            ->assertRedirect();

        $reply = IntranetBoardPost::query()->where('parent_id', $post->id)->firstOrFail();
        expect($reply->content)->toBe('Noted. Will ensure laboratory cold chain backups are checked.')
            ->and($reply->parent_id)->toBe($post->id)
            ->and($reply->author_id)->toBe((string) $rivalUser->uuid);

        // 6. Board detail view shows the post and reply
        $boardShow = $this->actingAs($regularUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('intranet.boards.show', $board));

        $boardShow->assertOk()
            ->assertSee('Scheduled Generator Servicing This Sunday')
            ->assertSee('Noted. Will ensure laboratory cold chain backups are checked.');

        // 7. Author deletes the reply
        $this->actingAs($rivalUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->delete(route('intranet.posts.destroy', $reply))
            ->assertRedirect();

        expect(IntranetBoardPost::query()->where('id', $reply->id)->exists())->toBeFalse();
    });

    test('manages events CRUD and employee RSVP lifecycle', function (): void {
        [$adminUser, $tenant] = createIntranetTestContext();

        // 1. Create Event
        $this->actingAs($adminUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('intranet.events.store'), [
                'title' => 'Q4 Leadership Strategy Retreat',
                'event_date' => now()->addDays(10)->toDateString(),
                'start_time' => '09:00',
                'end_time' => '17:00',
                'location' => 'Executive Conference Centre',
                'category' => IntranetEvent::CATEGORY_BOARD_MEETING,
                'description' => 'Annual strategic planning and performance scorecard review.',
            ])
            ->assertRedirect(route('intranet.index'));

        $event = IntranetEvent::query()->where('title', 'Q4 Leadership Strategy Retreat')->firstOrFail();
        expect($event->category)->toBe(IntranetEvent::CATEGORY_BOARD_MEETING);

        // 2. Edit Event
        $this->actingAs($adminUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->put(route('intranet.events.update', $event), [
                'title' => 'Q4 Leadership Strategy & Budget Retreat',
                'event_date' => now()->addDays(12)->toDateString(),
                'start_time' => '08:30',
                'end_time' => '16:30',
                'location' => 'Executive Conference Room A',
                'category' => IntranetEvent::CATEGORY_BOARD_MEETING,
                'description' => 'Updated strategy retreat with budget discussions.',
            ])
            ->assertRedirect(route('intranet.index'));

        $event->refresh();
        expect($event->title)->toBe('Q4 Leadership Strategy & Budget Retreat')
            ->and($event->location)->toBe('Executive Conference Room A');

        // 3. Employee RSVP: Attending -> Declined -> Tentative
        $employeeUser = User::factory()->create(['first_name' => 'Akosua', 'last_name' => 'Nurse']);
        $tenant->users()->attach($employeeUser->id);
        setPermissionsTeamId($tenant->id);
        $employeeUser->givePermissionTo('intranet.view');

        // RSVP: Attending
        $this->actingAs($employeeUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('intranet.events.rsvp', $event), [
                'status' => IntranetEventRsvp::STATUS_ATTENDING,
                'notes' => 'Will attend the morning session.',
            ])
            ->assertRedirect();

        $rsvp = IntranetEventRsvp::query()
            ->where('intranet_event_id', $event->id)
            ->where('user_id', (string) $employeeUser->uuid)
            ->firstOrFail();

        expect($rsvp->status)->toBe(IntranetEventRsvp::STATUS_ATTENDING)
            ->and($rsvp->notes)->toBe('Will attend the morning session.');

        // Update RSVP: Declined
        $this->actingAs($employeeUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('intranet.events.rsvp', $event), [
                'status' => IntranetEventRsvp::STATUS_DECLINED,
                'notes' => 'Emergency clinical shift scheduled.',
            ])
            ->assertRedirect();

        $rsvp->refresh();
        expect($rsvp->status)->toBe(IntranetEventRsvp::STATUS_DECLINED);

        // 4. Non-manager receives 403 attempting to delete or edit event
        $this->actingAs($employeeUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->delete(route('intranet.events.destroy', $event))
            ->assertForbidden();

        // 5. Admin deletes the event
        $this->actingAs($adminUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->delete(route('intranet.events.destroy', $event))
            ->assertRedirect(route('intranet.index'));

        expect(IntranetEvent::query()->where('id', $event->id)->exists())->toBeFalse()
            ->and(IntranetEventRsvp::query()->where('intranet_event_id', $event->id)->exists())->toBeFalse();
    });

    test('announcements feed and multi-entity search, filters and pagination', function (): void {
        [$adminUser, $tenant] = createIntranetTestContext();

        $board = IntranetMessageBoard::create([
            'title' => 'Policy & Governance Board',
            'slug' => 'policy-governance-board',
            'audience' => IntranetMessageBoard::AUDIENCE_INTERNAL,
            'is_pinned' => true,
        ]);

        IntranetBoardPost::create([
            'intranet_message_board_id' => $board->id,
            'author_id' => (string) $adminUser->uuid,
            'title' => 'Urgent Fire Drill Simulation Announced',
            'content' => 'Mandatory simulation across all blocks at 14:00.',
            'is_announcement' => true,
            'is_pinned' => true,
        ]);

        // 1. Announcements Feed View
        $announcementsResponse = $this->actingAs($adminUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('intranet.announcements', ['search' => 'Simulation']));

        $announcementsResponse->assertOk()
            ->assertSee('Urgent Fire Drill Simulation Announced');

        // 2. Hub index search & filters
        $hubFiltered = $this->actingAs($adminUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('intranet.index', [
                'board_search' => 'Policy',
                'board_audience' => 'internal',
                'event_timeframe' => 'all',
            ]));

        $hubFiltered->assertOk()
            ->assertSee('Policy &amp; Governance Board', false);
    });

    test('unauthenticated users cannot access intranet routes or leak internal content', function (): void {
        [$user, $tenant] = createIntranetTestContext();

        $board = IntranetMessageBoard::create([
            'title' => 'Confidential Board',
            'slug' => 'confidential-board',
            'audience' => IntranetMessageBoard::AUDIENCE_INTERNAL,
        ]);

        $event = IntranetEvent::create([
            'title' => 'Confidential Event',
            'event_date' => now()->addDay()->toDateString(),
            'location' => 'Board Room',
            'category' => IntranetEvent::CATEGORY_BOARD_MEETING,
        ]);

        // Guest to /intranet -> 302 redirect to login
        $this->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('intranet.index'))
            ->assertRedirect(route('login'));

        // Guest to /intranet/announcements -> 302 redirect to login
        $this->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('intranet.announcements'))
            ->assertRedirect(route('login'));

        // Guest to /intranet/boards/{board} -> 302 redirect to login
        $this->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('intranet.boards.show', $board))
            ->assertRedirect(route('login'));

        // Guest to /intranet/events -> 302 redirect to login
        $this->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('intranet.events.store'), [
                'title' => 'Hack Attempt',
            ])
            ->assertRedirect(route('login'));
    });

    test('IntranetSeeder seeds initial message boards, posts, events, and RSVPs cleanly', function (): void {
        [$user, $tenant] = createIntranetTestContext();

        $seeder = new IntranetSeeder();
        $seeder->run();

        expect(IntranetMessageBoard::query()->where('slug', 'institutional-executive-noticeboard')->exists())->toBeTrue()
            ->and(IntranetMessageBoard::query()->where('slug', 'staff-welfare-social-hub')->exists())->toBeTrue()
            ->and(IntranetEvent::query()->where('slug', 'all-hands-institutional-town-hall')->exists())->toBeTrue()
            ->and(IntranetEventRsvp::query()->exists())->toBeTrue();
    });
});

test('the intranet hub lists published controlled policies from Document Management', function (): void {
    [$user, $tenant] = createIntranetTestContext();

    app(ModuleManager::class)->enableForTenant('document-management', $tenant);

    $policy = App\Modules\DocumentManagement\Database\Factories\DocumentFactory::new()
        ->forTenant((string) $tenant->id)
        ->create([
            'title' => 'Staff Code of Conduct',
            'status' => 'published',
            'owner_id' => (string) $user->uuid,
        ]);

    $this->actingAs($user)
        ->get(route('intranet.index'))
        ->assertOk()
        ->assertSee('Staff Code of Conduct');

    expect($policy->status)->toBe('published');
});
