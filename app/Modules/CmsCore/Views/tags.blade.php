@extends('layouts.metronic.app')

@section('title', 'Tags')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">CMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Tags</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Label and cross-reference posts with tags.</p>
                </div>
                <div class="flex gap-2">
                    @can('cms.tags.manage')
                        <a class="kt-btn kt-btn-primary" href="{{ route('cms-core.tags.create') }}">New Tag</a>
                    @endcan
                    <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.index') }}">Back to Hub</a>
                </div>
            </div>
        </div>

        <div class="kt-card kt-card-grid min-w-full">
            <div class="kt-card-header flex-wrap gap-3">
                <h3 class="kt-card-title text-sm">All Tags</h3>
                <form class="flex items-center gap-2" method="GET" action="{{ route('cms-core.tags.index') }}">
                    <input class="kt-input w-full min-w-[220px] lg:w-[280px]" name="search" placeholder="Search tags" type="text" value="{{ $search }}" />
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
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @forelse($tags as $tag)
                                <tr>
                                    <td class="font-medium">{{ $tag->name }}</td>
                                    <td class="text-muted-foreground">{{ $tag->slug }}</td>
                                    <td>{{ $tag->posts_count }}</td>
                                    <td>
                                        @can('cms.tags.manage')
                                            <div class="flex items-center gap-2">
                                                <a class="kt-btn kt-btn-xs kt-btn-outline" href="{{ route('cms-core.tags.edit', $tag) }}">Edit</a>
                                                <form action="{{ route('cms-core.tags.destroy', $tag) }}" method="POST" onsubmit="return confirm('Delete this tag?')">
                                                    @csrf @method('DELETE')
                                                    <button class="kt-btn kt-btn-xs kt-btn-outline text-destructive" type="submit">Delete</button>
                                                </form>
                                            </div>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-8 text-center text-muted-foreground" colspan="4">No tags found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $tags->links() }}</div>
            </div>
        </div>
    </section>
@endsection
