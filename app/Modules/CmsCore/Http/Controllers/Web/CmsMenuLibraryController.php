<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Models\Menu;
use App\Modules\CmsCore\Models\MenuItem;
use App\Modules\CmsCore\Models\Post;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class CmsMenuLibraryController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('cms.menus.view'), 403);

        $search = mb_trim((string) $request->string('search'));

        $menus = Menu::query()
            ->withCount('items')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('cms-core::menus', [
            'menus' => $menus,
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()?->can('cms.menus.manage'), 403);

        return view('cms-core::menus-form', [
            'menu' => null,
            'menuItems' => collect(),
            'pages' => Post::query()->published()->forType('page')->orderBy('title')->get(['id', 'title', 'slug']),
            'posts' => Post::query()->published()->forType('post')->orderBy('title')->get(['id', 'title', 'slug']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(! $request->user()?->can('cms.menus.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $menu = Menu::query()->create($validated);

        $this->syncMenuItems($menu, $request);

        return redirect()
            ->route('cms-core.menus.edit', $menu)
            ->with('status', 'success')
            ->with('message', 'Menu created.');
    }

    public function edit(Request $request, Menu $menu): View
    {
        abort_if(! $request->user()?->can('cms.menus.manage'), 403);

        $menu->load(['items' => function ($query): void {
            $query->orderBy('sort_order');
        }, 'items.post:id,title,slug']);

        return view('cms-core::menus-form', [
            'menu' => $menu,
            'menuItems' => $menu->items->whereNull('parent_id')->sortBy('sort_order')->values(),
            'pages' => Post::query()->published()->forType('page')->orderBy('title')->get(['id', 'title', 'slug']),
            'posts' => Post::query()->published()->forType('post')->orderBy('title')->get(['id', 'title', 'slug']),
        ]);
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        abort_if(! $request->user()?->can('cms.menus.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
        ]);

        $menu->fill($validated);
        $menu->save();

        $this->syncMenuItems($menu, $request);

        return redirect()
            ->route('cms-core.menus.edit', $menu)
            ->with('status', 'success')
            ->with('message', 'Menu updated.');
    }

    public function destroy(Request $request, Menu $menu): RedirectResponse
    {
        abort_if(! $request->user()?->can('cms.menus.manage'), 403);

        $menu->items()->delete();
        $menu->delete();

        return redirect()
            ->route('cms-core.menus.index')
            ->with('status', 'success')
            ->with('message', 'Menu deleted.');
    }

    /**
     * Sync menu items from the request.
     *
     * Items are submitted as arrays: items[label][], items[url][], items[post_id][], items[sort_order][].
     */
    private function syncMenuItems(Menu $menu, Request $request): void
    {
        $request->validate([
            'items' => ['nullable', 'array'],
            'items.label' => ['nullable', 'array'],
            'items.label.*' => ['required', 'string', 'max:255'],
            'items.url.*' => ['nullable', 'string', 'max:500'],
            'items.post_id.*' => ['nullable', 'integer'],
        ]);

        // Delete existing items and recreate
        $menu->items()->delete();

        $labels = $request->input('items.label', []);
        $urls = $request->input('items.url', []);
        $postIds = $request->input('items.post_id', []);

        foreach ($labels as $index => $label) {
            if (empty($label)) {
                continue;
            }

            MenuItem::query()->create([
                'menu_id' => $menu->id,
                'label' => $label,
                'url' => $urls[$index] ?? null ?: null,
                'post_id' => ! empty($postIds[$index]) ? (int) $postIds[$index] : null,
                'sort_order' => $index,
            ]);
        }
    }
}
