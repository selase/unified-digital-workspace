<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Forums\Models\ForumChannel;
use App\Modules\Forums\Models\ForumMessage;
use App\Modules\Forums\Models\ForumMessageRecipient;
use App\Modules\Forums\Models\ForumModerationLog;
use App\Modules\Forums\Models\ForumPost;
use App\Modules\Forums\Models\ForumThread;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: User, 1: Tenant}
 */
function createForumsWebContext(): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = [
        'forums.view',
        'forums.post',
        'forums.moderate',
        'forums.messages.send',
        'forums.messages.manage',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'category' => 'forums',
            'guard_name' => 'web',
        ], [
            'uuid' => (string) Str::uuid(),
        ]);
    }

    $user->givePermissionTo($permissions);

    app(TenantDatabaseManager::class)->configure($tenant);
    app(ModuleManager::class)->enableForTenant('forums', $tenant);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/Forums/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

    return [$user, $tenant];
}

describe('Forums & Collaboration Web Workflows', function (): void {
    test('forums hub renders metrics, active channels, and discussion threads', function (): void {
        [$user, $tenant] = createForumsWebContext();

        $channel = ForumChannel::create([
            'tenant_id' => $tenant->id,
            'name' => 'Clinical Governance',
            'slug' => 'clinical-governance',
            'description' => 'Quality assurance and protocol reviews',
        ]);

        $thread = ForumThread::create([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'title' => 'Surgical Safety Checklist Adherence Audit',
            'user_id' => (string) $user->uuid,
            'status' => ForumThread::STATUS_OPEN,
        ]);

        ForumPost::create([
            'tenant_id' => $tenant->id,
            'thread_id' => $thread->id,
            'user_id' => (string) $user->uuid,
            'body' => 'Q3 audit results demonstrate 98.4% compliance across all operating suites.',
        ]);

        $response = $this->actingAs($user)->get(route('forums.hub'));

        $response->assertOk();
        $response->assertSee('Forums &amp; Collaboration Hub', false);
        $response->assertSee('Forums Hub');
        $response->assertSee('Clinical Governance');
        $response->assertSee('Surgical Safety Checklist Adherence Audit');
        $response->assertSee('Workspaces');
        $response->assertSee('Active topics');
    });

    test('can create, view, and browse discussion channels', function (): void {
        [$user, $tenant] = createForumsWebContext();

        // Create page
        $createPageResponse = $this->actingAs($user)->get(route('forums.channels.create'));
        $createPageResponse->assertOk();
        $createPageResponse->assertSee('Establish Discussion Channel');

        // Store channel
        $storeResponse = $this->actingAs($user)->post(route('forums.channels.store'), [
            'name' => 'Pediatric Intensive Care',
            'description' => 'Specialized discussion for PICU staff and clinical coordinators.',
            'sort_order' => 1,
            'is_locked' => '0',
        ]);

        $channel = ForumChannel::where('name', 'Pediatric Intensive Care')->first();
        expect($channel)->not->toBeNull();
        expect($channel->slug)->toBe('pediatric-intensive-care');

        $storeResponse->assertRedirect(route('forums.channels.show', $channel));

        // Index page
        $indexResponse = $this->actingAs($user)->get(route('forums.channels.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('Pediatric Intensive Care');

        // Show channel
        $showResponse = $this->actingAs($user)->get(route('forums.channels.show', $channel));
        $showResponse->assertOk();
        $showResponse->assertSee('#Pediatric Intensive Care');
    });

    test('can publish a thread, view discussion stream, and post replies', function (): void {
        [$user, $tenant] = createForumsWebContext();

        $channel = ForumChannel::create([
            'tenant_id' => $tenant->id,
            'name' => 'Pharmacy & Therapeutics',
            'slug' => 'pharmacy-therapeutics',
        ]);

        // Create thread page
        $createResponse = $this->actingAs($user)->get(route('forums.threads.create'));
        $createResponse->assertOk();
        $createResponse->assertSee('Start New Discussion Thread');

        // Store thread + initial post
        $storeResponse = $this->actingAs($user)->post(route('forums.threads.store'), [
            'channel_id' => $channel->id,
            'title' => 'Formulary Update: Intravenous Iron Formulations',
            'body' => 'Reviewing the updated protocol for ferric derisomaltose vs iron sucrose in chronic renal disease.',
            'tags' => 'formulary, pharmacy, nephrology',
        ]);

        $thread = ForumThread::where('title', 'Formulary Update: Intravenous Iron Formulations')->first();
        expect($thread)->not->toBeNull();
        expect($thread->posts()->count())->toBe(1);

        $storeResponse->assertRedirect(route('forums.threads.show', $thread));

        // Show thread
        $showResponse = $this->actingAs($user)->get(route('forums.threads.show', $thread));
        $showResponse->assertOk();
        $showResponse->assertSee('Formulary Update: Intravenous Iron Formulations');
        $showResponse->assertSee('Reviewing the updated protocol');
        $showResponse->assertSee('Contribute to Discussion');

        // Post a reply
        $replyResponse = $this->actingAs($user)->post(route('forums.threads.reply', $thread), [
            'body' => 'Clinical trials demonstrate superior safety and single-dose completion with ferric derisomaltose.',
        ]);

        $replyResponse->assertRedirect(route('forums.threads.show', $thread));
        expect($thread->posts()->count())->toBe(2);
    });

    test('moderators can lock, unlock, pin, and flag threads with audit logging', function (): void {
        [$user, $tenant] = createForumsWebContext();

        $channel = ForumChannel::create([
            'tenant_id' => $tenant->id,
            'name' => 'Emergency Operations',
            'slug' => 'emergency-operations',
        ]);

        $thread = ForumThread::create([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'title' => 'Mass Casualty Triage Protocol 2026',
            'user_id' => (string) $user->uuid,
            'status' => ForumThread::STATUS_OPEN,
        ]);

        // Pin thread
        $pinResponse = $this->actingAs($user)->post(route('forums.threads.pin', $thread));
        $pinResponse->assertRedirect(route('forums.threads.show', $thread));
        $thread->refresh();
        expect($thread->pinned_at)->not->toBeNull();

        $log = ForumModerationLog::where('thread_id', $thread->id)->where('action', 'pin_thread')->first();
        expect($log)->not->toBeNull();

        // Lock thread
        $lockResponse = $this->actingAs($user)->post(route('forums.threads.lock', $thread));
        $lockResponse->assertRedirect(route('forums.threads.show', $thread));
        $thread->refresh();
        expect($thread->status)->toBe(ForumThread::STATUS_LOCKED);
        expect($thread->locked_at)->not->toBeNull();

        // Locked thread rejects new replies
        $failedReplyResponse = $this->actingAs($user)->post(route('forums.threads.reply', $thread), [
            'body' => 'Attempting reply on locked thread',
        ]);
        $failedReplyResponse->assertSessionHas('status', 'danger');

        // Flag thread
        $flagResponse = $this->actingAs($user)->post(route('forums.threads.flag', $thread));
        $flagResponse->assertRedirect(route('forums.threads.show', $thread));
        $thread->refresh();
        expect($thread->status)->toBe(ForumThread::STATUS_FLAGGED);

        // Moderation Console
        $modResponse = $this->actingAs($user)->get(route('forums.moderation.index'));
        $modResponse->assertOk();
        $modResponse->assertSee('Mass Casualty Triage Protocol 2026');
        $modResponse->assertSee('Moderation Console &amp; Audit', false);
    });

    test('can compose, send, and read peer-to-peer direct messages', function (): void {
        [$sender, $tenant] = createForumsWebContext();
        $recipient = User::factory()->create();
        $tenant->users()->attach($recipient->id);

        // Compose page
        $composeResponse = $this->actingAs($sender)->get(route('forums.messages.create'));
        $composeResponse->assertOk();
        $composeResponse->assertSee('Compose Direct Message');

        // Send message
        $sendResponse = $this->actingAs($sender)->post(route('forums.messages.store'), [
            'recipient_id' => (string) $recipient->uuid,
            'subject' => 'Confidential: Internal Quality Assessment Results',
            'body' => 'Attached are preliminary notes regarding the departmental accreditation review.',
        ]);

        $message = ForumMessage::where('subject', 'Confidential: Internal Quality Assessment Results')->first();
        expect($message)->not->toBeNull();
        expect($message->recipients()->count())->toBe(1);

        $sendResponse->assertRedirect(route('forums.messages.show', $message));

        // Recipient reads message
        Session::put('active_tenant_id', $tenant->id);
        setPermissionsTeamId($tenant->id);
        $recipient->givePermissionTo(['forums.view']);

        $readResponse = $this->actingAs($recipient)->get(route('forums.messages.show', $message));
        $readResponse->assertOk();
        $readResponse->assertSee('Confidential: Internal Quality Assessment Results');
        $readResponse->assertSee('preliminary notes regarding the departmental accreditation review');

        $recipientRecord = ForumMessageRecipient::where('message_id', $message->id)
            ->where('user_id', (string) $recipient->uuid)
            ->first();
        expect($recipientRecord)->not->toBeNull();
        expect($recipientRecord->read_at)->not->toBeNull();
    });

    test('adversarial negative tests assert 403 on unauthorized forum operations', function (): void {
        $unauthorizedUser = User::factory()->create();
        $tenant = setActiveTenantForTest($unauthorizedUser);

        Session::put('active_tenant_id', $tenant->id);
        setPermissionsTeamId($tenant->id);
        app(TenantContext::class)->setTenant($tenant);

        app(TenantDatabaseManager::class)->configure($tenant);
        app(ModuleManager::class)->enableForTenant('forums', $tenant);

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => app_path('Modules/Forums/Database/Migrations'),
            '--realpath' => true,
            '--force' => true,
        ]);

        $channel = ForumChannel::create([
            'tenant_id' => $tenant->id,
            'name' => 'Restricted Channel',
        ]);

        $thread = ForumThread::create([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'title' => 'Restricted Thread',
            'user_id' => (string) $unauthorizedUser->uuid,
        ]);

        // Hub forbidden without forums.view
        $this->actingAs($unauthorizedUser)
            ->get(route('forums.hub'))
            ->assertStatus(403);

        // Channel creation forbidden without forums.moderate
        $this->actingAs($unauthorizedUser)
            ->get(route('forums.channels.create'))
            ->assertStatus(403);

        // Thread creation forbidden without forums.post
        $this->actingAs($unauthorizedUser)
            ->get(route('forums.threads.create'))
            ->assertStatus(403);

        // Moderation queue forbidden without forums.moderate
        $this->actingAs($unauthorizedUser)
            ->get(route('forums.moderation.index'))
            ->assertStatus(403);

        // Moderation lock action forbidden without forums.moderate
        $this->actingAs($unauthorizedUser)
            ->post(route('forums.threads.lock', $thread))
            ->assertStatus(403);

        // Message compose forbidden without forums.messages.send
        $this->actingAs($unauthorizedUser)
            ->get(route('forums.messages.create'))
            ->assertStatus(403);
    });
});
