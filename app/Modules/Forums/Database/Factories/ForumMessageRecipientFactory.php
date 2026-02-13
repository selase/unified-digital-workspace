<?php

declare(strict_types=1);

namespace App\Modules\Forums\Database\Factories;

use App\Modules\Forums\Models\ForumMessage;
use App\Modules\Forums\Models\ForumMessageRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumMessageRecipient>
 */
final class ForumMessageRecipientFactory extends Factory
{
    protected $model = ForumMessageRecipient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fake()->uuid(),
            'message_id' => ForumMessage::factory(),
            'user_id' => fake()->uuid(),
            'read_at' => null,
            'deleted_at' => null,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenantId,
        ]);
    }
}
