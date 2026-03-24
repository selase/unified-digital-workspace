<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SeedProductionMedia extends Command
{
    protected $signature = 'tgf:seed-media {--dry-run : Show what would be created without making changes}';

    protected $description = 'Create media records and link to posts for the TGF tenant (production one-time seed)';

    public function handle(): int
    {
        $tenant = Tenant::where('slug', 'thyroid-ghana-foundation')->first();

        if (! $tenant) {
            $this->error('TGF tenant not found.');

            return self::FAILURE;
        }

        $this->info("Tenant: {$tenant->name} (ID: {$tenant->id})");

        // Media files on S3 at tenants/thyroid-ghana-foundation/
        $mediaItems = [
            ['path' => 'cms/tgf-logo.jpg', 'title' => 'Thyroid Ghana Foundation Logo', 'filename' => 'tgf-logo.jpg', 'mime' => 'image/jpeg'],
            ['path' => 'cms/team/nana-adjoa.jpg', 'title' => 'Nana Adwoa Konadu Dsane', 'filename' => 'nana-adjoa.jpg', 'mime' => 'image/jpeg'],
            ['path' => 'cms/team/prof-patrick.jpg', 'title' => 'Rev. Prof. Patrick F. Ayeh-Kumi', 'filename' => 'prof-patrick.jpg', 'mime' => 'image/jpeg'],
            ['path' => 'cms/team/lesley-chartey.jpg', 'title' => 'Mr. Leslie Chartey Kumahlor', 'filename' => 'lesley-chartey.jpg', 'mime' => 'image/jpeg'],
            ['path' => 'cms/team/joyce-emefa.jpg', 'title' => 'Dr. Joyce Emefa Addo-Klah', 'filename' => 'joyce-emefa.jpg', 'mime' => 'image/jpeg'],
            ['path' => 'cms/team/frank-anyimedu.jpg', 'title' => 'Frank Anyimadu', 'filename' => 'frank-anyimedu.jpg', 'mime' => 'image/jpeg'],
            ['path' => 'cms/team/justice.jpg', 'title' => 'Justice Kwesi Baah', 'filename' => 'justice.jpg', 'mime' => 'image/jpeg'],
            ['path' => 'cms/news/akoma.jpg', 'title' => 'Ghana Health Awards Launch', 'filename' => 'akoma.jpg', 'mime' => 'image/jpeg'],
            ['path' => 'cms/news/WhatsApp-Image-2022-06-05-at-8.03.53-PM.jpeg', 'title' => 'Founder Nana Adwoa Konadu Dsane', 'filename' => 'WhatsApp-Image-2022-06-05-at-8.03.53-PM.jpeg', 'mime' => 'image/jpeg'],
            ['path' => 'cms/news/WhatsApp-Image-2022-06-05-at-8.04.45-PM.jpeg', 'title' => 'World Thyroid Awareness Week', 'filename' => 'WhatsApp-Image-2022-06-05-at-8.04.45-PM.jpeg', 'mime' => 'image/jpeg'],
            ['path' => 'cms/news/naadwoa_o.jpg', 'title' => 'Thyroid Disease and COVID-19', 'filename' => 'naadwoa_o.jpg', 'mime' => 'image/jpeg'],
            ['path' => 'cms/news/pfo.jpg', 'title' => 'Maiden Patients Forum', 'filename' => 'pfo.jpg', 'mime' => 'image/jpeg'],
            ['path' => 'cms/news/jeremi.jpg', 'title' => 'Jeremie Van-Garshong', 'filename' => 'jeremi.jpg', 'mime' => 'image/jpeg'],
            ['path' => 'cms/news/photo-ss-seed-faculty-ghana.jpg', 'title' => 'Thyroid Research in Ghana', 'filename' => 'photo-ss-seed-faculty-ghana.jpg', 'mime' => 'image/jpeg'],
            ['path' => 'cms/news/korle-bu.jpg', 'title' => 'Korle-Bu Teaching Hospital', 'filename' => 'korle-bu.jpg', 'mime' => 'image/jpeg'],
        ];

        // Post slug → media path mapping for featured images
        $postMediaMap = [
            'the-founder' => 'cms/team/nana-adjoa.jpg',
            'ghana-health-awards-honors-launched' => 'cms/news/akoma.jpg',
            'founder-featured-humble-beginnings' => 'cms/news/WhatsApp-Image-2022-06-05-at-8.03.53-PM.jpeg',
            'world-thyroid-awareness-week-launch' => 'cms/news/WhatsApp-Image-2022-06-05-at-8.04.45-PM.jpeg',
            'thyroid-disease-and-coronavirus' => 'cms/news/naadwoa_o.jpg',
            'maiden-patients-forum' => 'cms/news/pfo.jpg',
            'how-i-battled-cancer-jeremie-van-garshong' => 'cms/news/jeremi.jpg',
            'thyroid-disorders-central-ghana-iodization' => 'cms/news/photo-ss-seed-faculty-ghana.jpg',
            'thyroid-disorders-accra-korle-bu-study' => 'cms/news/korle-bu.jpg',
        ];

        $now = now();
        $mediaIdByPath = [];

        // 1. Create media records
        $this->info('Creating media records...');
        foreach ($mediaItems as $item) {
            $existing = DB::connection('landlord')->table('media')
                ->where('tenant_id', $tenant->id)
                ->where('path', $item['path'])
                ->first();

            if ($existing) {
                $this->line("  [SKIP] {$item['title']} — already exists (ID: {$existing->id})");
                $mediaIdByPath[$item['path']] = $existing->id;

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("  [DRY RUN] Would create: {$item['title']} ({$item['path']})");
                $mediaIdByPath[$item['path']] = 0;

                continue;
            }

            $id = DB::connection('landlord')->table('media')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'disk' => 'tenant',
                'path' => $item['path'],
                'original_filename' => $item['filename'],
                'filename' => pathinfo($item['filename'], PATHINFO_FILENAME),
                'extension' => pathinfo($item['filename'], PATHINFO_EXTENSION),
                'mime_type' => $item['mime'],
                'size_bytes' => 0,
                'title' => $item['title'],
                'is_public' => true,
                'uploaded_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->info("  ✓ Created #{$id}: {$item['title']}");
            $mediaIdByPath[$item['path']] = $id;
        }

        // 2. Link posts to featured media
        $this->newLine();
        $this->info('Linking posts to featured media...');
        foreach ($postMediaMap as $postSlug => $mediaPath) {
            $mediaId = $mediaIdByPath[$mediaPath] ?? null;

            if (! $mediaId) {
                $this->warn("  [SKIP] No media ID for path: {$mediaPath}");

                continue;
            }

            $post = DB::connection('landlord')->table('posts')
                ->where('tenant_id', $tenant->id)
                ->where('slug', $postSlug)
                ->first();

            if (! $post) {
                $this->warn("  [SKIP] Post not found: {$postSlug}");

                continue;
            }

            if ($post->featured_media_id) {
                $this->line("  [SKIP] {$post->title} — already has featured media (ID: {$post->featured_media_id})");

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("  [DRY RUN] Would link: {$post->title} → media #{$mediaId}");

                continue;
            }

            DB::connection('landlord')->table('posts')
                ->where('id', $post->id)
                ->update(['featured_media_id' => $mediaId]);

            $this->info("  ✓ {$post->title} → media #{$mediaId}");
        }

        $this->newLine();
        $this->info('Done!');

        return self::SUCCESS;
    }
}
