<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Http\Requests\CmsSettingsUpdateRequest;
use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\Menu;
use App\Modules\CmsCore\Models\Setting;
use App\Modules\CmsCore\Services\CmsThemeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CmsSettingsController extends Controller
{
    public function __construct(
        private readonly CmsThemeService $themeService
    ) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('cms.settings.manage'), 403);

        return view('cms-core::settings', [
            'settings' => $this->themeService->all(),
            'menus' => Menu::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'mediaItems' => Media::query()
                ->where('mime_type', 'like', 'image/%')
                ->orderByDesc('id')
                ->limit(50)
                ->get(['id', 'title', 'filename', 'original_filename']),
            'availableThemes' => $this->themeService->availableThemes(),
        ]);
    }

    public function update(CmsSettingsUpdateRequest $request): RedirectResponse
    {
        $fields = [
            'site_name', 'site_tagline', 'primary_color', 'secondary_color',
            'logo_media_id', 'favicon_media_id', 'header_menu_id', 'footer_menu_id',
            'footer_text', 'active_theme', 'custom_css',
        ];

        foreach ($fields as $key) {
            if (! $request->has($key)) {
                continue;
            }

            $value = $request->input($key);

            if ($value !== null && $value !== '') {
                Setting::query()->updateOrCreate(
                    ['group' => 'theme', 'key' => $key],
                    ['value' => $value]
                );
            } else {
                Setting::query()
                    ->where('group', 'theme')
                    ->where('key', $key)
                    ->delete();
            }
        }

        // Save homepage slides as JSON
        $slidesInput = $request->input('slides', []);
        $slides = collect($slidesInput)->filter(fn (array $s): bool => ! empty($s['title']))->values()->all();

        if (! empty($slides)) {
            Setting::query()->updateOrCreate(
                ['group' => 'theme', 'key' => 'homepage_slides'],
                ['value' => json_encode($slides)]
            );
        } else {
            Setting::query()
                ->where('group', 'theme')
                ->where('key', 'homepage_slides')
                ->delete();
        }

        return redirect()
            ->route('cms-core.settings.index')
            ->with('status', 'success')
            ->with('message', 'Website settings updated.');
    }
}
