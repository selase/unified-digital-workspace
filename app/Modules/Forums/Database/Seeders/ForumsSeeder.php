<?php

declare(strict_types=1);

namespace App\Modules\Forums\Database\Seeders;

use App\Models\User;
use App\Modules\Forums\Models\ForumChannel;
use App\Modules\Forums\Models\ForumPost;
use App\Modules\Forums\Models\ForumThread;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

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

        $user = User::query()->first();

        if (! $user) {
            return;
        }

        $channel = ForumChannel::factory()
            ->forTenant($tenant->id)
            ->create([
                'name' => 'General',
                'slug' => 'general',
            ]);

        $thread = ForumThread::factory()
            ->forTenant($tenant->id)
            ->create([
                'channel_id' => $channel->id,
                'user_id' => (string) $user->uuid,
                'title' => 'Welcome Thread',
                'slug' => 'welcome-thread',
            ]);

        ForumPost::factory()
            ->forTenant($tenant->id)
            ->create([
                'thread_id' => $thread->id,
                'user_id' => (string) $user->uuid,
                'body' => 'Welcome to the tenant forum.',
            ]);
    }
}
