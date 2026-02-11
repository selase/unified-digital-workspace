<?php

declare(strict_types=1);

namespace App\Modules\Memos\Database\Seeders;

use App\Models\User;
use App\Modules\Memos\Models\Memo;
use App\Modules\Memos\Models\MemoRecipient;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

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

        $user = User::query()->first();

        if (! $user) {
            return;
        }

        $memo = Memo::factory()
            ->forTenant($tenant->id)
            ->create([
                'sender_id' => $user->id,
            ]);

        MemoRecipient::factory()
            ->forTenant($tenant->id)
            ->create([
                'memo_id' => $memo->id,
                'recipient_id' => $user->id,
            ]);
    }
}
