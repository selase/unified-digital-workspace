<?php

declare(strict_types=1);

namespace App\Modules\Memos\Database\Factories;

use App\Models\User;
use App\Modules\Memos\Models\Memo;
use App\Modules\Memos\Models\MemoRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemoRecipient>
 */
final class MemoRecipientFactory extends Factory
{
    protected $model = MemoRecipient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'memo_id' => Memo::factory(),
            'tenant_id' => fake()->uuid(),
            'recipient_type' => 'user',
            'recipient_id' => User::factory(),
            'role' => MemoRecipient::ROLE_TO,
            'requires_ack' => true,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn () => [
            'tenant_id' => $tenantId,
        ]);
    }
}
