<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Database\Seeders;

use App\Models\User;
use App\Modules\CmsCore\Models\Category;
use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\Menu;
use App\Modules\CmsCore\Models\MenuItem;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostType;
use App\Modules\CmsCore\Models\Setting;
use App\Modules\CmsCore\Models\Tag;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

final class CmsCoreSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->bound(TenantContext::class)) {
            return;
        }

        $tenant = app(TenantContext::class)->getTenant();

        if (! $tenant) {
            return;
        }

        $tenantConnection = config('database.default_tenant_connection', 'tenant');

        if (! Schema::connection($tenantConnection)->hasTable('post_types')) {
            return;
        }

        $user = User::query()
            ->where('tenant_id', $tenant->id)
            ->first() ?? User::query()->first();

        if (! $user) {
            return;
        }

        $authorId = (string) $user->uuid;

        // ── Post Types ─────────────────────────────────────────
        $newsType = PostType::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'news',
        ], [
            'name' => 'News',
            'description' => 'Workspace announcements and product updates.',
            'supports' => [
                'excerpt' => true,
                'featured_media' => true,
            ],
            'is_active' => true,
        ]);

        $pageType = PostType::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'page',
        ], [
            'name' => 'Page',
            'description' => 'Static pages for the public website.',
            'supports' => [
                'excerpt' => true,
                'featured_media' => true,
                'parent' => true,
            ],
            'is_active' => true,
        ]);

        // ── Taxonomy ───────────────────────────────────────────
        $category = Category::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'operations',
        ], [
            'name' => 'Operations',
            'description' => 'Operational updates and circulars.',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $tag = Tag::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'workspace',
        ], [
            'name' => 'Workspace',
            'description' => 'Unified workspace related posts.',
        ]);

        // ── Media ──────────────────────────────────────────────
        $media = Media::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'path' => 'media/demo/dashboard-cover.jpg',
        ], [
            'disk' => 'public',
            'original_filename' => 'dashboard-cover.jpg',
            'filename' => 'dashboard-cover.jpg',
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 245_760,
            'checksum_sha256' => hash('sha256', 'dashboard-cover.jpg'),
            'width' => 1600,
            'height' => 900,
            'dominant_color' => '#1d4ed8',
            'alt_text' => 'Workspace dashboard cover image',
            'title' => 'Dashboard Cover',
            'uploaded_by' => $authorId,
            'source' => 'seed',
            'is_public' => true,
        ]);

        // ── News Posts ─────────────────────────────────────────
        $announcement = Post::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'post_type_id' => $newsType->id,
            'slug' => 'welcome-to-the-unified-workspace',
        ], [
            'title' => 'Welcome to the Unified Workspace',
            'status' => 'published',
            'excerpt' => 'Getting started guide for your new tenant workspace.',
            'body' => '<p>This workspace centralizes HRMS, documents, incidents, forums, memos, and quality monitoring into a single platform designed for your organization.</p>',
            'published_at' => now()->subDays(2),
            'author_id' => $authorId,
            'featured_media_id' => $media->id,
            'sort_order' => 1,
        ]);

        $releaseNotes = Post::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'post_type_id' => $newsType->id,
            'slug' => 'q1-platform-release-notes',
        ], [
            'title' => 'Q1 Platform Release Notes',
            'status' => 'published',
            'excerpt' => 'Highlights of the latest tenant module upgrades.',
            'body' => '<p>Major upgrades include metronic layouts, module hubs, and tenant analytics improvements.</p>',
            'published_at' => now()->subDay(),
            'author_id' => $authorId,
            'sort_order' => 2,
        ]);

        $announcement->categories()->syncWithoutDetaching([$category->id]);
        $announcement->tags()->syncWithoutDetaching([$tag->id]);
        $releaseNotes->categories()->syncWithoutDetaching([$category->id]);
        $releaseNotes->tags()->syncWithoutDetaching([$tag->id]);

        $announcement->media()->syncWithoutDetaching([
            $media->id => [
                'role' => 'cover',
                'sort_order' => 1,
            ],
        ]);

        // ── Static Pages ───────────────────────────────────────
        Post::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'post_type_id' => $pageType->id,
            'slug' => 'home',
        ], [
            'title' => 'Welcome',
            'status' => 'published',
            'excerpt' => 'Your organization\'s digital workspace.',
            'body' => '<p>Welcome to our organization. We are committed to delivering excellence through innovation, collaboration, and continuous improvement.</p>',
            'published_at' => now()->subDays(3),
            'author_id' => $authorId,
            'sort_order' => 1,
        ]);

        Post::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'post_type_id' => $pageType->id,
            'slug' => 'about',
        ], [
            'title' => 'About Us',
            'status' => 'published',
            'excerpt' => 'Learn more about our organization.',
            'body' => '<h2>Our Mission</h2><p>We strive to provide world-class services and foster a productive, innovative working environment for all our stakeholders.</p><h2>Our Values</h2><ul><li>Integrity and transparency</li><li>Innovation and excellence</li><li>Collaboration and teamwork</li></ul>',
            'published_at' => now()->subDays(3),
            'author_id' => $authorId,
            'sort_order' => 2,
        ]);

        Post::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'post_type_id' => $pageType->id,
            'slug' => 'contact',
        ], [
            'title' => 'Contact Us',
            'status' => 'published',
            'excerpt' => 'Get in touch with our team.',
            'body' => '<p>We would love to hear from you. Reach out to us through any of the following channels:</p><ul><li><strong>Email:</strong> info@example.com</li><li><strong>Phone:</strong> +1 (555) 123-4567</li><li><strong>Address:</strong> 123 Main Street, Suite 100</li></ul>',
            'published_at' => now()->subDays(3),
            'author_id' => $authorId,
            'sort_order' => 3,
        ]);

        // ── Main Navigation Menu ───────────────────────────────
        $menu = Menu::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'main-navigation',
        ], [
            'name' => 'Main Navigation',
        ]);

        MenuItem::query()->updateOrCreate([
            'menu_id' => $menu->id,
            'label' => 'Home',
        ], [
            'tenant_id' => $tenant->id,
            'url' => '/site',
            'sort_order' => 1,
        ]);

        MenuItem::query()->updateOrCreate([
            'menu_id' => $menu->id,
            'label' => 'About',
        ], [
            'tenant_id' => $tenant->id,
            'url' => '/site/about',
            'sort_order' => 2,
        ]);

        MenuItem::query()->updateOrCreate([
            'menu_id' => $menu->id,
            'label' => 'News',
        ], [
            'tenant_id' => $tenant->id,
            'url' => '/site/news',
            'sort_order' => 3,
        ]);

        MenuItem::query()->updateOrCreate([
            'menu_id' => $menu->id,
            'label' => 'Contact',
        ], [
            'tenant_id' => $tenant->id,
            'url' => '/site/contact',
            'sort_order' => 4,
        ]);

        // ── Theme Settings ─────────────────────────────────────
        Setting::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'group' => 'theme',
            'key' => 'site_name',
        ], [
            'value' => $tenant->name,
        ]);

        Setting::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'group' => 'theme',
            'key' => 'site_tagline',
        ], [
            'value' => 'Your Unified Digital Workspace',
        ]);

        Setting::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'group' => 'theme',
            'key' => 'primary_color',
        ], [
            'value' => '#1d4ed8',
        ]);
    }
}
