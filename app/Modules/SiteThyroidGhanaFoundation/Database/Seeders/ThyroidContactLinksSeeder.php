<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Database\Seeders;

use App\Models\Tenant;
use App\Modules\CmsCore\Models\Post;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Database\Seeder;

/**
 * Turns the plain-text email address and phone number on the action pages
 * (donate, volunteer, membership, partner) into tappable mailto:/tel: links,
 * with a pre-filled subject so the foundation can tell enquiries apart.
 * Text already inside a link is left alone, so it is safe to re-run.
 */
final class ThyroidContactLinksSeeder extends Seeder
{
    private const EMAIL = 'info@thyroidghanafoundation.org';

    private const PHONE_TEXT = '+233 (024) 337 6304';

    private const PHONE_URI = 'tel:+233243376304';

    /**
     * @var array<string, string>
     */
    private const SUBJECTS = [
        'donate' => 'Donation enquiry',
        'volunteer' => 'Volunteer enquiry',
        'membership' => 'Membership enquiry',
        'partner-us' => 'Partnership enquiry',
    ];

    public static function link(string $html, string $subject): string
    {
        $mailto = 'mailto:'.self::EMAIL.'?subject='.rawurlencode($subject);
        $insideLink = false;
        $result = '';

        foreach (preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if (str_starts_with($part, '<')) {
                if (preg_match('/^<a[\s>]/i', $part) === 1) {
                    $insideLink = true;
                } elseif (preg_match('/^<\/a\s*>/i', $part) === 1) {
                    $insideLink = false;
                }

                $result .= $part;

                continue;
            }

            $result .= $insideLink ? $part : self::linkText($part, $mailto);
        }

        return $result;
    }

    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'thyroid-ghana-foundation')->firstOrFail();

        app(TenantContext::class)->setTenant($tenant);
        app(TenantDatabaseManager::class)->configureShared();
        setPermissionsTeamId($tenant->id);

        foreach (self::SUBJECTS as $slug => $subject) {
            $page = Post::query()->where('slug', $slug)->first();

            if ($page === null) {
                continue;
            }

            $body = str_replace(
                'Contact us to discuss how we can work together.',
                'Contact us at '.self::EMAIL.' or call '.self::PHONE_TEXT.' to discuss how we can work together.',
                $page->body,
            );

            $linked = self::link($body, $subject);

            if ($linked !== $page->body) {
                $page->body = $linked;
                $page->save();
            }
        }
    }

    /**
     * The Post body cast stores "@" and "+" as HTML entities, so both forms are matched.
     */
    private static function linkText(string $text, string $mailto): string
    {
        $text = (string) preg_replace(
            '/info(?:@|&#0*64;)thyroidghanafoundation\.org/',
            '<a href="'.$mailto.'">'.self::EMAIL.'</a>',
            $text,
        );

        return (string) preg_replace(
            '/(?:\+|&#0*43;)233 \(024\) 337 6304/',
            '<a href="'.self::PHONE_URI.'">'.self::PHONE_TEXT.'</a>',
            $text,
        );
    }
}
