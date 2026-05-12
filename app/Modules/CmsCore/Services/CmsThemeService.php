<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Services;

use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\Menu;
use App\Modules\CmsCore\Models\Setting;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\File;

final class CmsThemeService
{
    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    /**
     * Get a theme setting value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * Get all theme settings as a flat array.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $tenant = $this->tenantContext->getTenant();

        if (! $tenant) {
            return $this->cache = [];
        }

        $settings = Setting::query()
            ->where('group', 'theme')
            ->pluck('value', 'key')
            ->map(fn (mixed $value): mixed => is_array($value) ? ($value[0] ?? $value) : $value)
            ->all();

        $this->cache = $settings;

        return $this->cache;
    }

    /**
     * Get the site name, falling back to tenant name.
     */
    public function siteName(): string
    {
        return (string) ($this->get('site_name') ?: $this->tenantContext->getTenant()?->name ?: 'Website');
    }

    /**
     * Get the site tagline.
     */
    public function siteTagline(): string
    {
        return (string) ($this->get('site_tagline') ?: '');
    }

    /**
     * Get the header menu with items eager-loaded.
     */
    public function headerMenu(): ?Menu
    {
        $menuId = $this->get('header_menu_id');

        if (! $menuId) {
            return Menu::query()->where('slug', 'main-navigation')->with('items.post.postType')->first();
        }

        return Menu::query()->with('items.post.postType')->find($menuId);
    }

    /**
     * Get the footer menu with items eager-loaded.
     */
    public function footerMenu(): ?Menu
    {
        $menuId = $this->get('footer_menu_id');

        if (! $menuId) {
            return null;
        }

        return Menu::query()->with('items.post.postType')->find($menuId);
    }

    /**
     * Get the logo Media record.
     */
    public function logo(): ?Media
    {
        $mediaId = $this->get('logo_media_id');

        return $mediaId ? Media::query()->find($mediaId) : null;
    }

    /**
     * Get the primary brand color.
     */
    public function primaryColor(): string
    {
        return (string) ($this->get('primary_color') ?: '#1d4ed8');
    }

    /**
     * Get the footer text.
     */
    public function footerText(): string
    {
        $default = '© '.date('Y').' '.$this->siteName().'. All rights reserved.';

        return (string) ($this->get('footer_text') ?: $default);
    }

    /**
     * Get the active theme slug.
     */
    public function activeTheme(): string
    {
        return (string) ($this->get('active_theme') ?: 'default');
    }

    /**
     * Get all available themes by scanning the themes directory.
     *
     * @return array<int, string>
     */
    public function availableThemes(): array
    {
        $themesPath = app_path('Modules/CmsCore/Views/themes');

        if (! File::isDirectory($themesPath)) {
            return ['default'];
        }

        $themes = collect(File::directories($themesPath))
            ->map(fn (string $dir): string => basename($dir))
            ->sort()
            ->values()
            ->all();

        return array_unique(array_merge(['default'], $themes));
    }

    /**
     * Get the secondary brand color.
     */
    public function secondaryColor(): string
    {
        return (string) ($this->get('secondary_color') ?: '#64748b');
    }

    /**
     * Get the font family.
     */
    public function fontFamily(): string
    {
        return (string) ($this->get('font_family') ?: 'Inter');
    }

    /**
     * Get custom CSS for the tenant.
     */
    public function customCss(): string
    {
        return (string) ($this->get('custom_css') ?: '');
    }

    /**
     * Get the favicon Media record.
     *
     * Prefers the higher-resolution site_icon when set, falling back to
     * the legacy favicon field for tenants that haven't migrated yet.
     */
    public function favicon(): ?Media
    {
        return $this->siteIcon();
    }

    /**
     * Get the master site icon (single high-res square source).
     *
     * Used to derive all platform-specific icons (web, iOS, Android, PWA).
     */
    public function siteIcon(): ?Media
    {
        $mediaId = $this->get('site_icon_media_id') ?: $this->get('favicon_media_id');

        return $mediaId ? Media::query()->find($mediaId) : null;
    }

    /**
     * Theme color used in the web manifest and browser-chrome meta tag.
     */
    public function themeColor(): string
    {
        return (string) ($this->get('theme_color') ?: $this->primaryColor());
    }
}
