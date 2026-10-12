<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Database\Seeders;

use App\Models\Tenant;
use App\Modules\CmsCore\Models\Post;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Database\Seeder;

/**
 * Corrects the founder's honorific ("Mrs." to "Dr.") in published content.
 *
 * Only references to the founder are rewritten; other people's titles such as
 * "Dr. Mrs. Josephine Akpalu" are left alone. Safe to run repeatedly.
 */
final class ThyroidFounderTitleSeeder extends Seeder
{
    private const FOUNDER = 'Nana Adwoa Konadu Dsane';

    public static function correct(string $text): string
    {
        $founder = preg_quote(self::FOUNDER, '/');

        return (string) preg_replace(
            [
                "/\\bMrs\\.? {$founder}/",
                "/{$founder} \\(Mrs\\.\\)/",
                '/\bMrs\.? Dsane\b/',
            ],
            [
                'Dr. '.self::FOUNDER,
                'Dr. '.self::FOUNDER,
                'Dr. Dsane',
            ],
            $text,
        );
    }

    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'thyroid-ghana-foundation')->firstOrFail();

        app(TenantContext::class)->setTenant($tenant);
        app(TenantDatabaseManager::class)->configureShared();
        setPermissionsTeamId($tenant->id);

        Post::query()
            ->where(function ($query): void {
                $query->where('body', 'like', '%Mrs%Dsane%')
                    ->orWhere('body', 'like', '%Dsane (Mrs.)%')
                    ->orWhere('excerpt', 'like', '%Mrs%Dsane%');
            })
            ->each(function (Post $post): void {
                $post->body = self::correct($post->body);

                if ($post->excerpt !== null) {
                    $post->excerpt = self::correct($post->excerpt);
                }

                $post->save();
            });
    }
}
