<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Forums\Models\ForumMessage;
use App\Modules\Forums\Models\ForumPost;
use App\Notifications\Forums\ForumMentionedNotification;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

/**
 * @return array{0: User, 1: Tenant, 2: User}
 */
function createForumsApiContext(): array
{
    $tenantDb = database_path('tenant_forums_testing.sqlite');
    if (file_exists($tenantDb)) {
        unlink($tenantDb);
    }
    touch($tenantDb);

    Config::set('database.connections.tenant', [
        'driver' => 'sqlite',
        'database' => $tenantDb,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    Config::set('database.default_tenant_connection', 'tenant');

    DB::purge('tenant');
    DB::reconnect('tenant');

    $moderator = User::factory()->create();
    $recipient = User::factory()->create();

    $tenant = setActiveTenantForTest($moderator, [
        'isolation_mode' => 'db_per_tenant',
        'db_driver' => 'sqlite',
        'meta' => [
            'database' => $tenantDb,
        ],
    ]);

    $tenant->users()->syncWithoutDetaching([$recipient->id]);

    switchToForumsTenantContext($tenant);

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

    $moderator->givePermissionTo($permissions);
    $recipient->givePermissionTo(['forums.view']);

    app(ModuleManager::class)->enableForTenant('forums', $tenant);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/Forums/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

    return [$moderator, $tenant, $recipient];
}

function switchToForumsTenantContext(Tenant $tenant): void
{
    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);
}

test('it manages channels and enforces tenant slug uniqueness', function (): void {
    [$moderator, $tenant] = createForumsApiContext();

    switchToForumsTenantContext($tenant);

    $channelResponse = actingAs($moderator, 'sanctum')->postJson('/api/forums/v1/channels', [
        'name' => 'General Discussion',
        'slug' => 'general-discussion',
    ]);

    $channelResponse->assertCreated();

    actingAs($moderator, 'sanctum')->postJson('/api/forums/v1/channels', [
        'name' => 'Another General',
        'slug' => 'general-discussion',
    ])->assertUnprocessable();

    $channelUuid = (string) $channelResponse->json('data.uuid');

    actingAs($moderator, 'sanctum')->putJson("/api/forums/v1/channels/{$channelUuid}", [
        'description' => 'Default tenant channel',
        'sort_order' => 5,
    ])->assertSuccessful()->assertJsonPath('data.sort_order', 5);

    actingAs($moderator, 'sanctum')->getJson('/api/forums/v1/channels')
        ->assertSuccessful()
        ->assertJsonFragment(['slug' => 'general-discussion']);
});

test('it supports thread posts, replies and best answer marking', function (): void {
    [$moderator, $tenant] = createForumsApiContext();

    switchToForumsTenantContext($tenant);

    $channel = actingAs($moderator, 'sanctum')->postJson('/api/forums/v1/channels', [
        'name' => 'Support',
        'slug' => 'support',
    ])->assertCreated();

    $channelUuid = (string) $channel->json('data.uuid');

    $thread = actingAs($moderator, 'sanctum')->postJson("/api/forums/v1/channels/{$channelUuid}/threads", [
        'title' => 'How to reset password?',
        'slug' => 'password-reset',
        'body' => 'Initial issue details',
    ])->assertCreated();

    $threadUuid = (string) $thread->json('data.uuid');

    $rootPost = ForumPost::query()->whereNull('parent_id')->firstOrFail();

    $reply = actingAs($moderator, 'sanctum')->postJson("/api/forums/v1/posts/{$rootPost->id}/reply", [
        'body' => 'Use the forgot password flow.',
    ])->assertCreated();

    $replyId = (int) $reply->json('data.id');

    actingAs($moderator, 'sanctum')->postJson("/api/forums/v1/posts/{$replyId}/mark-best")
        ->assertSuccessful()
        ->assertJsonPath('data.is_best_answer', true);

    actingAs($moderator, 'sanctum')->getJson("/api/forums/v1/threads/{$threadUuid}")
        ->assertSuccessful()
        ->assertJsonFragment(['is_best_answer' => true]);
});

test('it filters threads by channel, tag, and author', function (): void {
    [$moderator, $tenant, $recipient] = createForumsApiContext();

    switchToForumsTenantContext($tenant);
    $recipient->givePermissionTo(['forums.post']);

    $generalChannelUuid = (string) actingAs($moderator, 'sanctum')->postJson('/api/forums/v1/channels', [
        'name' => 'General',
        'slug' => 'general',
    ])->json('data.uuid');

    $supportChannelUuid = (string) actingAs($moderator, 'sanctum')->postJson('/api/forums/v1/channels', [
        'name' => 'Support',
        'slug' => 'support',
    ])->json('data.uuid');

    actingAs($moderator, 'sanctum')->postJson("/api/forums/v1/channels/{$generalChannelUuid}/threads", [
        'title' => 'General update',
        'slug' => 'general-update',
        'tags' => ['announcements'],
        'body' => 'General thread body',
    ])->assertCreated();

    actingAs($recipient, 'sanctum')->postJson("/api/forums/v1/channels/{$supportChannelUuid}/threads", [
        'title' => 'Need help',
        'slug' => 'need-help',
        'tags' => ['support'],
        'body' => 'Support thread body',
    ])->assertCreated();

    actingAs($moderator, 'sanctum')->getJson("/api/forums/v1/threads?channel={$supportChannelUuid}")
        ->assertSuccessful()
        ->assertJsonFragment(['slug' => 'need-help'])
        ->assertJsonMissing(['slug' => 'general-update']);

    actingAs($moderator, 'sanctum')->getJson('/api/forums/v1/threads?tag=announcements')
        ->assertSuccessful()
        ->assertJsonFragment(['slug' => 'general-update'])
        ->assertJsonMissing(['slug' => 'need-help']);

    actingAs($moderator, 'sanctum')->getJson('/api/forums/v1/threads?user='.(string) $recipient->uuid)
        ->assertSuccessful()
        ->assertJsonFragment(['slug' => 'need-help'])
        ->assertJsonMissing(['slug' => 'general-update']);
});

test('it enforces unique reactions per post and user', function (): void {
    [$moderator, $tenant] = createForumsApiContext();

    switchToForumsTenantContext($tenant);

    $channelUuid = (string) actingAs($moderator, 'sanctum')->postJson('/api/forums/v1/channels', [
        'name' => 'Ideas',
        'slug' => 'ideas',
    ])->json('data.uuid');

    actingAs($moderator, 'sanctum')->postJson("/api/forums/v1/channels/{$channelUuid}/threads", [
        'title' => 'Suggestion board',
        'slug' => 'suggestion-board',
        'body' => 'Share improvements.',
    ])->assertCreated();

    $post = ForumPost::query()->whereNull('parent_id')->firstOrFail();

    actingAs($moderator, 'sanctum')->postJson("/api/forums/v1/posts/{$post->id}/react", [
        'type' => 'like',
    ])->assertCreated();

    actingAs($moderator, 'sanctum')->postJson("/api/forums/v1/posts/{$post->id}/react", [
        'type' => 'like',
    ])->assertCreated();

    $this->assertDatabaseCount('forum_reactions', 1, 'tenant');
});

test('it enforces moderation permission and writes moderation logs', function (): void {
    [$moderator, $tenant, $recipient] = createForumsApiContext();

    switchToForumsTenantContext($tenant);

    $channelUuid = (string) actingAs($moderator, 'sanctum')->postJson('/api/forums/v1/channels', [
        'name' => 'Operations',
        'slug' => 'operations',
    ])->json('data.uuid');

    $threadUuid = (string) actingAs($moderator, 'sanctum')->postJson("/api/forums/v1/channels/{$channelUuid}/threads", [
        'title' => 'Pending issue',
        'slug' => 'pending-issue',
        'body' => 'Investigate this case.',
    ])->json('data.uuid');

    switchToForumsTenantContext($tenant);
    actingAs($recipient, 'sanctum')->postJson("/api/forums/v1/threads/{$threadUuid}/moderate", [
        'action' => 'lock',
    ])->assertForbidden();

    switchToForumsTenantContext($tenant);
    actingAs($moderator, 'sanctum')->postJson("/api/forums/v1/threads/{$threadUuid}/moderate", [
        'action' => 'lock',
        'reason' => 'Pause while reviewing',
    ])->assertSuccessful();

    $this->assertDatabaseHas('forum_moderation_logs', [
        'action' => 'lock',
        'reason' => 'Pause while reviewing',
    ], 'tenant');
});

test('it exposes moderation flags queue and logs to moderators only', function (): void {
    [$moderator, $tenant, $recipient] = createForumsApiContext();

    switchToForumsTenantContext($tenant);

    $channelUuid = (string) actingAs($moderator, 'sanctum')->postJson('/api/forums/v1/channels', [
        'name' => 'Security',
        'slug' => 'security',
    ])->json('data.uuid');

    $threadUuid = (string) actingAs($moderator, 'sanctum')->postJson("/api/forums/v1/channels/{$channelUuid}/threads", [
        'title' => 'Suspicious activity',
        'slug' => 'suspicious-activity',
        'body' => 'Review this anomaly.',
    ])->json('data.uuid');

    switchToForumsTenantContext($tenant);
    actingAs($moderator, 'sanctum')->postJson("/api/forums/v1/threads/{$threadUuid}/moderate", [
        'action' => 'flag',
        'reason' => 'Needs investigation',
    ])->assertSuccessful();

    switchToForumsTenantContext($tenant);
    actingAs($recipient, 'sanctum')->getJson('/api/forums/v1/moderation/flags')
        ->assertForbidden();

    switchToForumsTenantContext($tenant);
    actingAs($moderator, 'sanctum')->getJson('/api/forums/v1/moderation/flags')
        ->assertSuccessful()
        ->assertJsonFragment(['uuid' => $threadUuid])
        ->assertJsonFragment(['status' => 'flagged']);

    switchToForumsTenantContext($tenant);
    actingAs($moderator, 'sanctum')->getJson('/api/forums/v1/moderation/logs?action=flag')
        ->assertSuccessful()
        ->assertJsonFragment(['action' => 'flag'])
        ->assertJsonFragment(['reason' => 'Needs investigation']);
});

test('it provides moderation overview and action options for moderators only', function (): void {
    [$moderator, $tenant, $recipient] = createForumsApiContext();

    switchToForumsTenantContext($tenant);

    $channelUuid = (string) actingAs($moderator, 'sanctum')->postJson('/api/forums/v1/channels', [
        'name' => 'Governance',
        'slug' => 'governance',
    ])->json('data.uuid');

    $threadUuid = (string) actingAs($moderator, 'sanctum')->postJson("/api/forums/v1/channels/{$channelUuid}/threads", [
        'title' => 'Policy update',
        'slug' => 'policy-update',
        'body' => 'Pending moderation',
    ])->json('data.uuid');

    actingAs($moderator, 'sanctum')->postJson("/api/forums/v1/threads/{$threadUuid}/moderate", [
        'action' => 'flag',
        'reason' => 'Needs legal review',
    ])->assertSuccessful();

    actingAs($moderator, 'sanctum')->postJson("/api/forums/v1/threads/{$threadUuid}/moderate", [
        'action' => 'lock',
        'reason' => 'Pause discussion',
    ])->assertSuccessful();

    switchToForumsTenantContext($tenant);
    actingAs($recipient, 'sanctum')->getJson('/api/forums/v1/moderation/overview')
        ->assertForbidden();

    switchToForumsTenantContext($tenant);
    actingAs($moderator, 'sanctum')->getJson('/api/forums/v1/moderation/overview')
        ->assertSuccessful()
        ->assertJsonPath('counts.flagged_threads', 0)
        ->assertJsonPath('counts.locked_threads', 1)
        ->assertJsonPath('counts.actions_last_24h', 2);

    switchToForumsTenantContext($tenant);
    actingAs($moderator, 'sanctum')->getJson('/api/forums/v1/moderation/actions')
        ->assertSuccessful()
        ->assertJsonFragment(['actions' => ['pin', 'unpin', 'lock', 'unlock', 'flag', 'delete']]);
});

test('it handles messaging delivery and read receipts', function (): void {
    [$moderator, $tenant, $recipient] = createForumsApiContext();

    switchToForumsTenantContext($tenant);

    $messageResponse = actingAs($moderator, 'sanctum')->postJson('/api/forums/v1/messages', [
        'subject' => 'Action Required',
        'body' => 'Please acknowledge this notice.',
        'recipient_user_ids' => [(string) $recipient->uuid],
    ]);

    $messageResponse->assertCreated();

    $messageUuid = (string) $messageResponse->json('data.uuid');

    switchToForumsTenantContext($tenant);
    actingAs($recipient, 'sanctum')->getJson('/api/forums/v1/messages')
        ->assertSuccessful()
        ->assertJsonFragment(['uuid' => $messageUuid]);

    switchToForumsTenantContext($tenant);
    actingAs($recipient, 'sanctum')->postJson("/api/forums/v1/messages/{$messageUuid}/read")
        ->assertSuccessful();

    $this->assertDatabaseHas('forum_message_recipients', [
        'message_id' => $messageResponse->json('data.id'),
        'user_id' => (string) $recipient->uuid,
    ], 'tenant');

    $readAt = DB::connection('tenant')
        ->table('forum_message_recipients')
        ->where('message_id', $messageResponse->json('data.id'))
        ->where('user_id', (string) $recipient->uuid)
        ->value('read_at');

    expect($readAt)->not->toBeNull();
});

test('it filters messages by box, search query, visibility scope, and unread state', function (): void {
    [$moderator, $tenant, $recipient] = createForumsApiContext();

    $sender = User::factory()->create();
    $tenant->users()->syncWithoutDetaching([$sender->id]);
    $sender->givePermissionTo(['forums.view', 'forums.messages.send']);
    $moderator->givePermissionTo(['forums.messages.send']);

    switchToForumsTenantContext($tenant);
    $messageOne = actingAs($moderator, 'sanctum')->postJson('/api/forums/v1/messages', [
        'subject' => 'Budget Memo',
        'body' => 'Draft budget notes',
        'recipient_user_ids' => [(string) $recipient->uuid],
        'visibility' => ['scope' => 'direct'],
    ])->assertCreated();

    switchToForumsTenantContext($tenant);
    $messageTwo = actingAs($moderator, 'sanctum')->postJson('/api/forums/v1/messages', [
        'subject' => 'Townhall Invite',
        'body' => 'Organization-wide update',
        'recipient_user_ids' => [(string) $recipient->uuid],
        'visibility' => ['scope' => 'organization'],
    ])->assertCreated();

    switchToForumsTenantContext($tenant);
    actingAs($sender, 'sanctum')->postJson('/api/forums/v1/messages', [
        'subject' => 'Support Request',
        'body' => 'Need help with dashboard',
        'recipient_user_ids' => [(string) $recipient->uuid],
        'visibility' => ['scope' => 'department'],
    ])->assertCreated();

    switchToForumsTenantContext($tenant);
    actingAs($recipient, 'sanctum')
        ->postJson('/api/forums/v1/messages/'.$messageOne->json('data.uuid').'/read')
        ->assertSuccessful();

    switchToForumsTenantContext($tenant);
    actingAs($recipient, 'sanctum')->getJson('/api/forums/v1/messages?box=inbox&q=Townhall')
        ->assertSuccessful()
        ->assertJsonFragment(['subject' => 'Townhall Invite'])
        ->assertJsonMissing(['subject' => 'Budget Memo']);

    switchToForumsTenantContext($tenant);
    actingAs($recipient, 'sanctum')->getJson('/api/forums/v1/messages?scope=organization')
        ->assertSuccessful()
        ->assertJsonFragment(['subject' => 'Townhall Invite'])
        ->assertJsonMissing(['subject' => 'Budget Memo']);

    switchToForumsTenantContext($tenant);
    actingAs($recipient, 'sanctum')->getJson('/api/forums/v1/messages?unread=1')
        ->assertSuccessful()
        ->assertJsonMissing(['subject' => 'Budget Memo']);

    switchToForumsTenantContext($tenant);
    actingAs($moderator, 'sanctum')->getJson('/api/forums/v1/messages?box=sent')
        ->assertSuccessful()
        ->assertJsonFragment(['subject' => 'Budget Memo'])
        ->assertJsonFragment(['subject' => 'Townhall Invite']);

    $organizationMessage = ForumMessage::query()
        ->where('subject', 'Townhall Invite')
        ->firstOrFail();

    expect($organizationMessage->visibility['scope'] ?? null)->toBe('organization');
});

test('it writes mention notifications for mentioned tenant users', function (): void {
    [$moderator, $tenant, $recipient] = createForumsApiContext();

    switchToForumsTenantContext($tenant);

    $channelUuid = (string) actingAs($moderator, 'sanctum')->postJson('/api/forums/v1/channels', [
        'name' => 'Announcements',
        'slug' => 'announcements',
    ])->json('data.uuid');

    actingAs($moderator, 'sanctum')->postJson("/api/forums/v1/channels/{$channelUuid}/threads", [
        'title' => 'Rollout',
        'slug' => 'rollout',
        'body' => 'Please check this @'.(string) $recipient->uuid,
    ])->assertCreated();

    $notification = DatabaseNotification::query()
        ->where('type', ForumMentionedNotification::class)
        ->where('notifiable_type', User::class)
        ->where('notifiable_id', (string) $recipient->id)
        ->latest()
        ->first();

    expect($notification)->not->toBeNull();
    expect((array) $notification?->data)->toMatchArray([
        'module' => 'forums',
        'type' => 'forum_mention',
    ]);
});

test('it rejects messages when recipients are outside tenant membership', function (): void {
    [$moderator, $tenant] = createForumsApiContext();

    $externalUser = User::factory()->create();
    $moderator->givePermissionTo(['forums.messages.send']);

    switchToForumsTenantContext($tenant);
    actingAs($moderator, 'sanctum')->postJson('/api/forums/v1/messages', [
        'subject' => 'Confidential',
        'body' => 'Do not share externally',
        'recipient_user_ids' => [(string) $externalUser->uuid],
    ])->assertUnprocessable();
});

test('it rejects mixed recipients when one is outside tenant membership', function (): void {
    [$moderator, $tenant, $recipient] = createForumsApiContext();

    $externalUser = User::factory()->create();
    $moderator->givePermissionTo(['forums.messages.send']);

    switchToForumsTenantContext($tenant);
    actingAs($moderator, 'sanctum')->postJson('/api/forums/v1/messages', [
        'subject' => 'Tenant-only delivery',
        'body' => 'Mixed recipient payload should fail.',
        'recipient_user_ids' => [(string) $recipient->uuid, (string) $externalUser->uuid],
    ])->assertUnprocessable();

    expect(ForumMessage::query()->count())->toBe(0);
});
