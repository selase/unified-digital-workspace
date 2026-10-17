<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Forums\Models\ForumMessage;
use App\Modules\Forums\Models\ForumMessageRecipient;
use App\Modules\Memos\Models\Memo;
use App\Modules\Memos\Models\MemoRecipient;
use App\Notifications\DirectMessageReceivedNotification;
use App\Notifications\MemoDispatchedNotification;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: User, 1: Tenant}
 */
function createMessagingNotificationContext(): array
{
    $sender = User::factory()->create([
        'first_name' => 'Sender',
        'last_name' => 'Director',
    ]);
    $tenant = setActiveTenantForTest($sender);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = [
        'memos.view',
        'memos.create',
        'memos.update',
        'memos.send',
        'forums.view',
        'forums.messages.send',
        'forums.messages.manage',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ], [
            'category' => Str::before($permission, '.'),
            'uuid' => (string) Str::uuid(),
        ]);
    }

    $sender->givePermissionTo($permissions);

    app(TenantDatabaseManager::class)->configure($tenant);
    app(ModuleManager::class)->enableForTenant('memos', $tenant);
    app(ModuleManager::class)->enableForTenant('forums', $tenant);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/Memos/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/Forums/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

    return [$sender, $tenant];
}

describe('Phase 15 & 16: Multi-Recipient To/CC/BCC Privacy & Unified Notifications', function (): void {
    test('memos support To, CC, and BCC recipients, dispatch notifications, and strictly enforce BCC privacy isolation', function (): void {
        Notification::fake();

        [$sender, $tenant] = createMessagingNotificationContext();

        $toUser = User::factory()->create(['first_name' => 'AliceTo', 'last_name' => 'Primary', 'tenant_id' => $tenant->id]);
        $ccUser = User::factory()->create(['first_name' => 'BobCc', 'last_name' => 'Carbon', 'tenant_id' => $tenant->id]);
        $bccUser1 = User::factory()->create(['first_name' => 'CharlieBccOne', 'last_name' => 'BlindOne', 'tenant_id' => $tenant->id]);
        $bccUser2 = User::factory()->create(['first_name' => 'DianaBccTwo', 'last_name' => 'BlindTwo', 'tenant_id' => $tenant->id]);

        foreach ([$toUser, $ccUser, $bccUser1, $bccUser2] as $recipientUser) {
            $recipientUser->tenants()->syncWithoutDetaching([$tenant->id]);
            setPermissionsTeamId($tenant->id);
            $recipientUser->givePermissionTo(['memos.view']);
        }

        // Create and send Memo with To, CC, and two BCC recipients
        $storeResponse = $this->actingAs($sender)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('memos.store'), [
                'subject' => 'Confidential Executive Directive',
                'body' => 'Please review the attached institutional restructuring roadmap.',
                'action' => 'send',
                'recipients' => [
                    ['type' => MemoRecipient::TYPE_USER, 'id' => (string) $toUser->id, 'role' => MemoRecipient::ROLE_TO],
                ],
                'cc_user_ids' => [$ccUser->id],
                'bcc_user_ids' => [$bccUser1->id, $bccUser2->id],
            ]);

        $storeResponse->assertRedirect();

        $memo = Memo::query()->where('subject', 'Confidential Executive Directive')->firstOrFail();
        expect($memo->recipients()->count())->toBe(4);

        // Assert notifications were dispatched to all 4 recipients
        Notification::assertSentTo([$toUser, $ccUser, $bccUser1, $bccUser2], MemoDispatchedNotification::class);

        // 1. Sender sees all recipients including both BCCs
        $senderView = $this->actingAs($sender)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('memos.show', $memo));
        $senderView->assertOk();
        $senderView->assertSee('AliceTo Primary');
        $senderView->assertSee('BobCc Carbon');
        $senderView->assertSee('CharlieBccOne BlindOne');
        $senderView->assertSee('DianaBccTwo BlindTwo');

        // 2. Adversarial check: To recipient sees To and CC, but NEVER sees any BCC recipient
        $toView = $this->actingAs($toUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('memos.show', $memo));
        $toView->assertOk();
        $toView->assertSee('AliceTo Primary');
        $toView->assertSee('BobCc Carbon');
        $toView->assertDontSee('CharlieBccOne');
        $toView->assertDontSee('DianaBccTwo');
        $toView->assertDontSee($bccUser1->email);
        $toView->assertDontSee($bccUser2->email);

        // 3. Adversarial check: CC recipient sees To and CC, but NEVER sees any BCC recipient
        $ccView = $this->actingAs($ccUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('memos.show', $memo));
        $ccView->assertOk();
        $ccView->assertDontSee('CharlieBccOne');
        $ccView->assertDontSee('DianaBccTwo');

        // 4. Adversarial check: BCC recipient 1 sees To, CC, and their own BCC entry, but NEVER sees BCC recipient 2
        $bcc1View = $this->actingAs($bccUser1)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('memos.show', $memo));
        $bcc1View->assertOk();
        $bcc1View->assertSee('AliceTo Primary');
        $bcc1View->assertSee('BobCc Carbon');
        $bcc1View->assertSee('CharlieBccOne BlindOne');
        $bcc1View->assertDontSee('DianaBccTwo');
        $bcc1View->assertDontSee($bccUser2->email);
    });

    test('forums direct messages support To, CC, and BCC, dispatch notifications, and isolate BCC identities', function (): void {
        Notification::fake();

        [$sender, $tenant] = createMessagingNotificationContext();

        $toUser = User::factory()->create(['first_name' => 'EvanTo', 'last_name' => 'Direct', 'tenant_id' => $tenant->id]);
        $ccUser = User::factory()->create(['first_name' => 'FionaCc', 'last_name' => 'Copied', 'tenant_id' => $tenant->id]);
        $bccUser1 = User::factory()->create(['first_name' => 'GeorgeBccOne', 'last_name' => 'HiddenOne', 'tenant_id' => $tenant->id]);
        $bccUser2 = User::factory()->create(['first_name' => 'HannahBccTwo', 'last_name' => 'HiddenTwo', 'tenant_id' => $tenant->id]);
        $intruder = User::factory()->create(['first_name' => 'Intruder', 'last_name' => 'Outsider', 'tenant_id' => $tenant->id]);

        foreach ([$toUser, $ccUser, $bccUser1, $bccUser2, $intruder] as $u) {
            $u->tenants()->syncWithoutDetaching([$tenant->id]);
            setPermissionsTeamId($tenant->id);
            $u->givePermissionTo(['forums.view']);
        }

        $response = $this->actingAs($sender)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('forums.messages.store'), [
                'subject' => 'Board Committee Briefing',
                'body' => 'Meeting minutes are ready for internal review.',
                'to_user_ids' => [(string) $toUser->uuid],
                'cc_user_ids' => [(string) $ccUser->uuid],
                'bcc_user_ids' => [(string) $bccUser1->uuid, (string) $bccUser2->uuid],
            ]);

        $response->assertRedirect();

        $message = ForumMessage::query()->where('subject', 'Board Committee Briefing')->firstOrFail();

        expect($message->recipients()->where('role', ForumMessageRecipient::ROLE_TO)->count())->toBe(1)
            ->and($message->recipients()->where('role', ForumMessageRecipient::ROLE_CC)->count())->toBe(1)
            ->and($message->recipients()->where('role', ForumMessageRecipient::ROLE_BCC)->count())->toBe(2);

        Notification::assertSentTo([$toUser, $ccUser, $bccUser1, $bccUser2], DirectMessageReceivedNotification::class);

        // To recipient cannot see BCC recipients
        $toShow = $this->actingAs($toUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('forums.messages.show', $message));
        $toShow->assertOk();
        $toShow->assertSee('EvanTo Direct');
        $toShow->assertSee('FionaCc Copied');
        $toShow->assertDontSee('GeorgeBccOne');
        $toShow->assertDontSee('HannahBccTwo');

        // BCC recipient 1 sees themselves, To, and CC, but NOT BCC recipient 2
        $bcc1Show = $this->actingAs($bccUser1)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('forums.messages.show', $message));
        $bcc1Show->assertOk();
        $bcc1Show->assertSee('GeorgeBccOne HiddenOne');
        $bcc1Show->assertDontSee('HannahBccTwo');

        // Adversarial check: non-recipient intruder receives 403
        $this->actingAs($intruder)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('forums.messages.show', $message))
            ->assertForbidden();
    });

    test('unified notification center lists database notifications, marks read, and rejects cross-user tampering', function (): void {
        [$sender, $tenant] = createMessagingNotificationContext();

        $recipient = User::factory()->create(['first_name' => 'Recipient', 'last_name' => 'User', 'tenant_id' => $tenant->id]);
        $otherUser = User::factory()->create(['first_name' => 'Other', 'last_name' => 'User', 'tenant_id' => $tenant->id]);
        $recipient->tenants()->syncWithoutDetaching([$tenant->id]);
        $otherUser->tenants()->syncWithoutDetaching([$tenant->id]);

        $memo = Memo::create([
            'tenant_id' => $tenant->id,
            'sender_id' => (string) $sender->uuid,
            'subject' => 'Database Notification Verification Memo',
            'body' => 'Testing real database notification storage.',
            'status' => Memo::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $recipient->notifyNow(new MemoDispatchedNotification(
            (string) $memo->uuid,
            (string) $memo->subject,
            $sender->displayName(),
            MemoRecipient::ROLE_TO
        ));

        $notification = $recipient->unreadNotifications()->first();
        expect($notification)->not->toBeNull();

        // Recipient can fetch JSON notifications
        $indexResponse = $this->actingAs($recipient)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->getJson(route('notifications.index'));

        $indexResponse->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.title', 'Official Memo (to): Database Notification Verification Memo');

        // Adversarial negative test: Other user cannot mark Recipient's notification as read
        $this->actingAs($otherUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->postJson(route('notifications.mark-read', $notification->id))
            ->assertNotFound();

        expect($notification->fresh()?->read_at)->toBeNull();

        // Recipient marks their own notification as read
        $this->actingAs($recipient)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->postJson(route('notifications.mark-read', $notification->id))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        expect($notification->fresh()?->read_at)->not->toBeNull();
    });
});
