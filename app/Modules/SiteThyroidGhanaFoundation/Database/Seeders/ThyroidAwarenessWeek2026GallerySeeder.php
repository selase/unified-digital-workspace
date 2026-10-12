<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Database\Seeders;

use App\Models\Tenant;
use App\Modules\CmsCore\Models\Media;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use App\Services\Tenancy\TenantStorageManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Builds the International Thyroid Awareness Week 2026 photo gallery shown on
 * the Media Gallery page.
 *
 * A photo belongs to the gallery through its media metadata
 * (`gallery`, `order`, `caption`), so the page needs no extra tables and
 * editors can reorder or caption photos from the media library data.
 * Story photos already imported by ThyroidAwarenessWeek2026Seeder are reused;
 * additional highlights are imported from the bundled gallery folder.
 * Safe to re-run.
 */
final class ThyroidAwarenessWeek2026GallerySeeder extends Seeder
{
    public const GALLERY = 'awareness-week-2026';

    private const STORY_DIRECTORY = 'cms/news/awareness-week-2026';

    private const GALLERY_DIRECTORY = 'cms/gallery/awareness-week-2026';

    /**
     * Ordered gallery. "story:" photos were imported with the story;
     * "gallery:" photos ship in assets/awareness-week-2026/gallery.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const PHOTOS = [
        ['story:vip-group-photo', 'Guests and organisers of International Thyroid Awareness Week 2026'],
        ['story:full-house-audience', 'A full house at the University of Ghana Medical Centre'],
        ['gallery:03-on-stage-at-ugmc', 'On stage at the University of Ghana Medical Centre'],
        ['story:opening-session', 'The opening session'],
        ['gallery:01-speaker-at-the-podium', 'Remarks from the podium'],
        ['gallery:02-guest-speaker', 'A guest speaker addressing participants'],
        ['story:nutrition-presentation', 'The Thyroid and Nutrition keynote'],
        ['gallery:05-presenter-beside-thyroid-model', 'A presenter beside the thyroid model'],
        ['story:thyroid-management-presentation', 'Treatment options for thyroid disorders'],
        ['gallery:06-presenter-explaining-slides', 'Presenting treatment options on screen'],
        ['gallery:07-speaker-closing-remarks', 'A speaker at the podium'],
        ['gallery:13-addressing-participants', 'Addressing participants'],
        ['story:patient-testimonies', 'Patients shared their journeys'],
        ['story:expert-panel', 'The expert panel'],
        ['story:healthy-eating-demonstration', 'A live healthy-eating demonstration'],
        ['story:healthy-plate', 'A thyroid-friendly plate from the demonstration'],
        ['gallery:04-attentive-audience', 'Attentive guests in the audience'],
        ['story:mds-lancet-partners', 'MDS-Lancet Laboratories, a long-standing partner'],
        ['story:vip-guests', 'Guests on the red carpet'],
        ['gallery:08-red-carpet-pair', 'Smiles on the red carpet'],
        ['gallery:09-red-carpet-embrace', 'Smiles on the red carpet'],
        ['gallery:10-red-carpet-trio', 'Guests on the red carpet'],
        ['gallery:11-red-carpet-guests', 'Guests on the red carpet'],
        ['gallery:12-guest-at-the-backdrop', 'A guest at the event backdrop'],
        ['story:founder-closing-call', 'Dr. Nana Adwoa Konadu Dsane, Founder'],
        ['gallery:14-founder-by-the-railing', 'Dr. Nana Adwoa Konadu Dsane, Founder'],
        ['gallery:15-founder-portrait-closeup', 'Dr. Nana Adwoa Konadu Dsane, Founder'],
        ['story:volunteers-team', 'The volunteers and organising team'],
        ['story:participants-group-photo', 'Participants, speakers and partners'],
    ];

    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'thyroid-ghana-foundation')->firstOrFail();

        app(TenantContext::class)->setTenant($tenant);
        app(TenantDatabaseManager::class)->configureShared();
        app(TenantStorageManager::class)->configure($tenant);
        setPermissionsTeamId($tenant->id);

        $author = $tenant->users()->first();

        if ($author === null) {
            throw new RuntimeException('The tenant has no users to attribute the photos to.');
        }

        $uploaderId = (string) $author->uuid;

        foreach (self::PHOTOS as $position => [$reference, $caption]) {
            [$source, $name] = explode(':', $reference, 2);

            $media = $source === 'story'
                ? $this->storyPhoto($name)
                : $this->importPhoto($name, $caption, $uploaderId);

            $media->update(['metadata' => array_merge($media->metadata ?? [], [
                'gallery' => self::GALLERY,
                'order' => $position + 1,
                'caption' => $caption,
            ])]);
        }
    }

    private function storyPhoto(string $name): Media
    {
        return Media::query()->where('path', self::STORY_DIRECTORY."/{$name}.jpg")->first()
            ?? throw new RuntimeException("Story photo [{$name}] has not been imported; run ThyroidAwarenessWeek2026Seeder.");
    }

    private function importPhoto(string $name, string $caption, string $uploaderId): Media
    {
        $sourcePath = __DIR__."/assets/awareness-week-2026/gallery/{$name}.jpg";

        if (! is_file($sourcePath)) {
            throw new RuntimeException("Missing bundled photo: {$sourcePath}");
        }

        $storagePath = self::GALLERY_DIRECTORY."/{$name}.jpg";
        Storage::disk('tenant')->put($storagePath, (string) file_get_contents($sourcePath));
        [$width, $height] = (array) getimagesize($sourcePath);

        return Media::query()->updateOrCreate(
            ['path' => $storagePath],
            [
                'disk' => 'tenant',
                'original_filename' => "{$name}.jpg",
                'filename' => "{$name}.jpg",
                'extension' => 'jpg',
                'mime_type' => 'image/jpeg',
                'size_bytes' => (int) filesize($sourcePath),
                'checksum_sha256' => hash_file('sha256', $sourcePath) ?: null,
                'width' => $width,
                'height' => $height,
                'title' => 'International Thyroid Awareness Week 2026 — '.$caption,
                'alt_text' => $caption.' at International Thyroid Awareness Week 2026.',
                'uploaded_by' => $uploaderId,
                'source' => 'upload',
                'is_public' => true,
            ]
        );
    }
}
