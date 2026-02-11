<?php

declare(strict_types=1);

namespace App\Modules\Memos\Database\Factories;

use App\Models\User;
use App\Modules\Memos\Models\Memo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Memo>
 */
final class MemoFactory extends Factory
{
    protected $model = Memo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fake()->uuid(),
            'sender_id' => User::factory(),
            'subject' => fake()->sentence(6),
            'body' => fake()->paragraph(),
            'status' => Memo::STATUS_DRAFT,
            'verification_attempts' => 0,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn () => [
            'tenant_id' => $tenantId,
        ]);
    }
}
