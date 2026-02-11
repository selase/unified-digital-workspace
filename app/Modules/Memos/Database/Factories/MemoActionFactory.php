<?php

declare(strict_types=1);

namespace App\Modules\Memos\Database\Factories;

use App\Models\User;
use App\Modules\Memos\Models\Memo;
use App\Modules\Memos\Models\MemoAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemoAction>
 */
final class MemoActionFactory extends Factory
{
    protected $model = MemoAction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'memo_id' => Memo::factory(),
            'tenant_id' => fake()->uuid(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->sentence(12),
            'assigned_to_id' => fake()->boolean(70) ? User::factory() : null,
            'due_at' => fake()->optional()->dateTimeBetween('+1 day', '+2 weeks'),
            'status' => 'open',
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn () => [
            'tenant_id' => $tenantId,
        ]);
    }
}
