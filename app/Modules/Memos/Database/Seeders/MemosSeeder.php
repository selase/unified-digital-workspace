<?php

declare(strict_types=1);

namespace App\Modules\Memos\Database\Seeders;

use App\Models\User;
use App\Modules\Memos\Models\Memo;
use App\Modules\Memos\Models\MemoAction;
use App\Modules\Memos\Models\MemoMinute;
use App\Modules\Memos\Models\MemoRecipient;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

final class MemosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
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

        if (! Schema::connection($tenantConnection)->hasTable('memos')) {
            return;
        }

        $user = User::query()
            ->where('tenant_id', $tenant->id)
            ->first() ?? User::query()->first();

        if (! $user) {
            return;
        }

        $memo = Memo::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'subject' => 'Quarterly Operations Memo',
        ], [
            'sender_id' => (string) $user->uuid,
            'body' => 'Please review operational priorities and acknowledge by end of week.',
            'status' => Memo::STATUS_SENT,
            'signed_at' => now()->subDay(),
            'verified_at' => now()->subDay(),
            'sent_at' => now()->subDay(),
            'verification_attempts' => 1,
            'verification_code_hash' => hash('sha256', '123456'),
            'verification_sent_at' => now()->subDays(2),
            'verification_expires_at' => now()->subDays(2)->addMinutes(10),
        ]);

        MemoRecipient::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'memo_id' => $memo->id,
            'recipient_type' => MemoRecipient::TYPE_USER,
            'recipient_id' => (string) $user->uuid,
            'role' => MemoRecipient::ROLE_TO,
        ], [
            'requires_ack' => true,
            'acknowledged_at' => now()->subHours(18),
            'acknowledged_by_id' => (string) $user->uuid,
        ]);

        MemoRecipient::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'memo_id' => $memo->id,
            'recipient_type' => MemoRecipient::TYPE_TENANT,
            'role' => MemoRecipient::ROLE_CC,
        ], [
            'recipient_id' => null,
            'requires_ack' => false,
        ]);

        MemoMinute::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'memo_id' => $memo->id,
            'body' => 'Acknowledged. Team leads to submit execution updates every Friday.',
        ], [
            'author_id' => (string) $user->uuid,
        ]);

        MemoAction::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'memo_id' => $memo->id,
            'title' => 'Publish department implementation timeline',
        ], [
            'description' => 'Each unit should provide its execution timeline for the quarter.',
            'assigned_to_id' => (string) $user->uuid,
            'due_at' => now()->addDays(5),
            'status' => 'open',
        ]);
    }
}
