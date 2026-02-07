<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Database\Factories;

use App\Models\User;
use App\Modules\CmsCore\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
final class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = Str::slug(fake()->words(2, true)).'.jpg';

        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => fake()->uuid(),
            'disk' => 'public',
            'path' => "media/{$filename}",
            'original_filename' => $filename,
            'filename' => $filename,
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => fake()->numberBetween(2048, 1048576),
            'checksum_sha256' => fake()->sha256(),
            'width' => 1200,
            'height' => 800,
            'duration_seconds' => null,
            'bitrate' => null,
            'fps' => null,
            'dominant_color' => '#111827',
            'blurhash' => null,
            'metadata' => null,
            'alt_text' => fake()->sentence(),
            'caption' => null,
            'title' => fake()->sentence(3),
            'description' => null,
            'uploaded_by' => User::factory(),
            'source' => 'upload',
            'is_public' => true,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn () => [
            'tenant_id' => $tenantId,
            'uploaded_by' => User::factory()->state(['tenant_id' => $tenantId]),
        ]);
    }
}
