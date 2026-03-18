<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class CmsTagController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('cms.tags.view'), 403);

        $search = mb_trim((string) $request->string('search'));

        $tags = Tag::query()
            ->withCount('posts')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('cms-core::tags', [
            'tags' => $tags,
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()?->can('cms.tags.manage'), 403);

        return view('cms-core::tags-form', ['tag' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(! $request->user()?->can('cms.tags.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        Tag::query()->create($validated);

        return redirect()
            ->route('cms-core.tags.index')
            ->with('status', 'success')
            ->with('message', 'Tag created.');
    }

    public function edit(Request $request, Tag $tag): View
    {
        abort_if(! $request->user()?->can('cms.tags.manage'), 403);

        return view('cms-core::tags-form', ['tag' => $tag]);
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        abort_if(! $request->user()?->can('cms.tags.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $tag->fill($validated);
        $tag->save();

        return redirect()
            ->route('cms-core.tags.index')
            ->with('status', 'success')
            ->with('message', 'Tag updated.');
    }

    public function destroy(Request $request, Tag $tag): RedirectResponse
    {
        abort_if(! $request->user()?->can('cms.tags.manage'), 403);

        $tag->posts()->detach();
        $tag->delete();

        return redirect()
            ->route('cms-core.tags.index')
            ->with('status', 'success')
            ->with('message', 'Tag deleted.');
    }
}
