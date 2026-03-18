<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class CmsCategoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('cms.categories.view'), 403);

        $search = mb_trim((string) $request->string('search'));

        $categories = Category::query()
            ->withCount('posts')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('cms-core::categories', [
            'categories' => $categories,
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()?->can('cms.categories.manage'), 403);

        return view('cms-core::categories-form', [
            'category' => null,
            'parentCategories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(! $request->user()?->can('cms.categories.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $validated['is_active'] = $request->boolean('is_active', true);

        Category::query()->create($validated);

        return redirect()
            ->route('cms-core.categories.index')
            ->with('status', 'success')
            ->with('message', 'Category created.');
    }

    public function edit(Request $request, Category $category): View
    {
        abort_if(! $request->user()?->can('cms.categories.manage'), 403);

        return view('cms-core::categories-form', [
            'category' => $category,
            'parentCategories' => Category::query()
                ->where('id', '!=', $category->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        abort_if(! $request->user()?->can('cms.categories.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $category->fill($validated);
        $category->save();

        return redirect()
            ->route('cms-core.categories.index')
            ->with('status', 'success')
            ->with('message', 'Category updated.');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        abort_if(! $request->user()?->can('cms.categories.manage'), 403);

        $category->posts()->detach();
        $category->delete();

        return redirect()
            ->route('cms-core.categories.index')
            ->with('status', 'success')
            ->with('message', 'Category deleted.');
    }
}
