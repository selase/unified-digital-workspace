<?php

declare(strict_types=1);

namespace App\Modules\Forums\Database\Seeders;

use App\Models\User;
use App\Modules\Forums\Models\ForumChannel;
use App\Modules\Forums\Models\ForumMessage;
use App\Modules\Forums\Models\ForumMessageRecipient;
use App\Modules\Forums\Models\ForumModerationLog;
use App\Modules\Forums\Models\ForumPost;
use App\Modules\Forums\Models\ForumReaction;
use App\Modules\Forums\Models\ForumThread;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

final class ForumsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->bound(TenantContext::class)) {
            return;
        }

        $tenant = app(TenantContext::class)->getTenant();

        if (! $tenant) {
            return;
        }

        $tenantConnection = config('database.default_tenant_connection', 'tenant');

        if (! Schema::connection($tenantConnection)->hasTable('forum_channels')) {
            return;
        }

        $user = User::query()
            ->where('tenant_id', $tenant->id)
            ->first() ?? User::query()->first();

        if (! $user) {
            return;
        }

        $channel = ForumChannel::factory()
            ->forTenant($tenant->id)
            ->create([
                'name' => 'General',
                'slug' => 'general',
            ]);

        $thread = ForumThread::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'slug' => 'welcome-thread',
        ], [
            'user_id' => (string) $user->uuid,
            'title' => 'Welcome Thread',
            'status' => ForumThread::STATUS_OPEN,
            'tags' => ['welcome', 'workspace'],
        ]);

        $post = ForumPost::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'thread_id' => $thread->id,
            'user_id' => (string) $user->uuid,
            'body' => 'Welcome to the tenant forum.',
        ], [
            'parent_id' => null,
            'is_best_answer' => false,
        ]);

        ForumReaction::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'post_id' => $post->id,
            'user_id' => (string) $user->uuid,
            'type' => 'like',
        ]);

        $flaggedThread = ForumThread::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'slug' => 'policy-clarification',
        ], [
            'user_id' => (string) $user->uuid,
            'title' => 'Policy Clarification Needed',
            'status' => ForumThread::STATUS_FLAGGED,
            'tags' => ['moderation'],
            'metadata' => ['seed' => true],
        ]);

        ForumModerationLog::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'thread_id' => $flaggedThread->id,
            'action' => 'flag',
        ], [
            'post_id' => null,
            'moderator_id' => (string) $user->uuid,
            'reason' => 'Flagged for review in demo data.',
            'metadata' => ['source' => 'seeder'],
            'created_at' => now()->subHour(),
        ]);

        $message = ForumMessage::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'subject' => 'Welcome to Forums',
            'sender_id' => (string) $user->uuid,
        ], [
            'body' => 'This is a seeded direct message to preview the message center.',
            'visibility' => ['tenant_wide' => false],
            'metadata' => ['source' => 'seeder'],
        ]);

        ForumMessageRecipient::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'message_id' => $message->id,
            'user_id' => (string) $user->uuid,
        ], [
            'read_at' => now()->subMinutes(5),
            'deleted_at' => null,
        ]);
    }
}
