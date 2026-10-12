<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Database\Seeders;

use App\Models\Tenant;
use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\Post;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Database\Seeder;

/**
 * Brings the founder's details in published content up to date: the honorific
 * ("Mrs." to "Dr.") and her completed PhD, plus her current portrait on the
 * Founder page.
 *
 * Only references to the founder are rewritten; other people's titles such as
 * "Dr. Mrs. Josephine Akpalu" are left alone. Safe to run repeatedly.
 */
final class ThyroidFounderDetailsSeeder extends Seeder
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
                '/She is pursuing a PhD in Human Resources at the University of South Africa, researching/',
            ],
            [
                'Dr. '.self::FOUNDER,
                'Dr. '.self::FOUNDER,
                'Dr. Dsane',
                'She holds a PhD in Human Resources from the University of South Africa, where her research examined',
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
                    ->orWhere('body', 'like', '%pursuing a PhD%')
                    ->orWhere('excerpt', 'like', '%Mrs%Dsane%');
            })
            ->each(function (Post $post): void {
                $post->body = self::correct($post->body);

                if ($post->excerpt !== null) {
                    $post->excerpt = self::correct($post->excerpt);
                }

                $post->save();
            });

        $portrait = Media::query()->where('path', ThyroidAwarenessWeek2026Seeder::FOUNDER_PORTRAIT_PATH)->first();

        if ($portrait !== null) {
            Post::query()->where('slug', 'the-founder')->update(['featured_media_id' => $portrait->id]);
        }
    }
}
