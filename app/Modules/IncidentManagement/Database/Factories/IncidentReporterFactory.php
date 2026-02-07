<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Database\Factories;

use App\Modules\IncidentManagement\Models\IncidentReporter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IncidentReporter>
 */
final class IncidentReporterFactory extends Factory
{
    protected $model = IncidentReporter::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => fake()->uuid(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'organization' => fake()->company(),
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn () => [
            'tenant_id' => $tenantId,
        ]);
    }
}
