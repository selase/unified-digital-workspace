@extends('layouts.metronic.app')

@section('title', 'Categories')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">CMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Categories</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Organize posts with hierarchical categories.</p>
                </div>
                <div class="flex gap-2">
                    @can('cms.categories.manage')
                        <a class="kt-btn kt-btn-primary" href="{{ route('cms-core.categories.create') }}">New Category</a>
                    @endcan
                    <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.index') }}">Back to Hub</a>
                </div>
            </div>
        </div>

        <div class="kt-card kt-card-grid min-w-full">
            <div class="kt-card-header flex-wrap gap-3">
                <h3 class="kt-card-title text-sm">All Categories</h3>
                <form class="flex items-center gap-2" method="GET" action="{{ route('cms-core.categories.index') }}">
                    <input class="kt-input w-full min-w-[220px] lg:w-[280px]" name="search" placeholder="Search categories" type="text" value="{{ $search }}" />
                    <button class="kt-btn kt-btn-outline" type="submit">Filter</button>
                </form>
            </div>
            <div class="kt-card-content">
                <div class="kt-scrollable-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Posts</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @forelse($categories as $category)
                                <tr>
                                    <td class="font-medium">{{ $category->name }}</td>
                                    <td class="text-muted-foreground">{{ $category->slug }}</td>
                                    <td>{{ $category->posts_count }}</td>
                                    <td>
                                        <span class="kt-badge {{ $category->is_active ? 'kt-badge-success' : 'kt-badge-outline' }}">
                                            {{ $category->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>
                                        @can('cms.categories.manage')
                                            <div class="flex items-center gap-2">
                                                <a class="kt-btn kt-btn-xs kt-btn-outline" href="{{ route('cms-core.categories.edit', $category) }}">Edit</a>
                                                <form action="{{ route('cms-core.categories.destroy', $category) }}" method="POST" onsubmit="return confirm('Delete this category?')">
                                                    @csrf @method('DELETE')
                                                    <button class="kt-btn kt-btn-xs kt-btn-outline text-destructive" type="submit">Delete</button>
                                                </form>
                                            </div>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-8 text-center text-muted-foreground" colspan="5">No categories found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $categories->links() }}</div>
            </div>
        </div>
    </section>
@endsection
