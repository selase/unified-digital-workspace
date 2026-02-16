<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Forums\Models\ForumChannel;
use App\Modules\Forums\Models\ForumMessage;
use App\Modules\Forums\Models\ForumMessageRecipient;
use App\Modules\Forums\Models\ForumModerationLog;
use App\Modules\Forums\Models\ForumThread;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: App\Models\Tenant, 1: string}
 */
function setupForumsWebTenant(User $user): array
{
    $tenantDb = database_path('tenant_forums_web_testing.sqlite');
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

    $tenant = setActiveTenantForTest($user, [
        'isolation_mode' => 'db_per_tenant',
        'db_driver' => 'sqlite',
        'meta' => [
            'database' => $tenantDb,
        ],
    ]);

    return [$tenant, $tenantDb];
}

test('forums hub is accessible when module is enabled for tenant', function (): void {
    $user = User::factory()->create();
    [$tenant] = setupForumsWebTenant($user);

    Permission::firstOrCreate([
        'name' => 'forums.view',
        'category' => 'forums',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
    ]);
    Permission::firstOrCreate([
        'name' => 'forums.moderate',
        'category' => 'forums',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
    ]);

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo(['forums.view', 'forums.moderate']);

    app(TenantDatabaseManager::class)->configure($tenant);
    app(ModuleManager::class)->enableForTenant('forums', $tenant);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/Forums/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

    $channel = ForumChannel::query()->create([
        'name' => 'General',
        'slug' => 'general',
    ]);

    $thread = ForumThread::query()->create([
        'channel_id' => $channel->id,
        'title' => 'Welcome thread',
        'slug' => 'welcome-thread',
        'user_id' => (string) $user->uuid,
    ]);

    ForumModerationLog::query()->create([
        'thread_id' => $thread->id,
        'moderator_id' => (string) $user->uuid,
        'action' => 'pin',
        'reason' => 'Pinned for visibility',
    ]);

    $message = ForumMessage::query()->create([
        'sender_id' => (string) $user->uuid,
        'subject' => 'Community update',
        'body' => 'Please read and respond in your unit.',
        'visibility' => ['scope' => 'unit'],
    ]);

    ForumMessageRecipient::query()->create([
        'message_id' => $message->id,
        'user_id' => (string) $user->uuid,
    ]);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/forums/hub')
        ->assertSuccessful()
        ->assertSee('Forums Hub')
        ->assertSee('Welcome thread');

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/forums/channels')
        ->assertSuccessful()
        ->assertSee('Channel Directory')
        ->assertSee('General');

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/forums/threads')
        ->assertSuccessful()
        ->assertSee('Thread Queue')
        ->assertSee('Welcome thread');

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/forums/messages')
        ->assertSuccessful()
        ->assertSee('Message Center')
        ->assertSee('Community update');

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/forums/moderation')
        ->assertSuccessful()
        ->assertSee('Moderation Queue')
        ->assertSee('Pinned for visibility');
});

test('forums hub is forbidden when module is not enabled for tenant', function (): void {
    $user = User::factory()->create();
    [$tenant] = setupForumsWebTenant($user);

    Permission::firstOrCreate([
        'name' => 'forums.view',
        'category' => 'forums',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
    ]);

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo('forums.view');

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/forums/hub')
        ->assertForbidden();
});

test('forums moderation queue requires moderation permission', function (): void {
    $user = User::factory()->create();
    [$tenant] = setupForumsWebTenant($user);

    Permission::firstOrCreate([
        'name' => 'forums.view',
        'category' => 'forums',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
    ]);

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo('forums.view');

    app(TenantDatabaseManager::class)->configure($tenant);
    app(ModuleManager::class)->enableForTenant('forums', $tenant);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/Forums/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/forums/moderation')
        ->assertForbidden();
});
