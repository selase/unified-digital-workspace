@extends('layouts.metronic.app')

@section('title', 'CMS Posts')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">CMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Post Library</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Publishing board for drafted, scheduled, and live content entries.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.index') }}">Back to Hub</a>
                    @can('cms.posts.create')
                        <a class="kt-btn kt-btn-primary" href="{{ route('cms-core.posts.create') }}">
                            <i class="ki-filled ki-plus text-base"></i>
                            New Post
                        </a>
                    @endcan
                </div>
            </div>
        </div>

        <div class="kt-card kt-card-grid min-w-full">
            <div class="kt-card-header py-5 flex-wrap gap-3">
                <div class="flex items-center gap-2.5">
                    <h3 class="kt-card-title">Publishing Queue</h3>
                    <span class="kt-badge kt-badge-outline">{{ $posts->total() }} posts</span>
                </div>
                <form class="flex w-full lg:w-auto flex-wrap items-center gap-2.5 lg:justify-end" method="GET" action="{{ route('cms-core.posts.index') }}">
                    <label class="kt-input w-full md:w-[290px]">
                        <i class="ki-filled ki-magnifier"></i>
                        <input
                            name="search"
                            placeholder="Search title, slug, excerpt"
                            type="text"
                            value="{{ $search }}"
                        />
                    </label>
                    <div class="w-full sm:w-[180px]">
                        <select class="kt-select" data-kt-select="true" data-kt-select-placeholder="Any status" name="status">
                            <option value="">Any status</option>
                            <option value="draft" @selected($status === 'draft')>Draft</option>
                            <option value="published" @selected($status === 'published')>Published</option>
                            <option value="scheduled" @selected($status === 'scheduled')>Scheduled</option>
                            <option value="archived" @selected($status === 'archived')>Archived</option>
                        </select>
                    </div>
                    <div class="w-full sm:w-[220px]">
                        <select class="kt-select" data-kt-select="true" data-kt-select-placeholder="All post types" name="post_type_id">
                            <option value="">All post types</option>
                            @foreach($postTypes as $type)
                                <option value="{{ $type->id }}" @selected($postTypeId === $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="kt-btn kt-btn-outline" type="submit">Filter</button>
                </form>
            </div>
            <div class="kt-card-content">
                <div class="kt-scrollable-x-auto">
                    <table class="kt-table kt-table-border align-middle">
                        <thead>
                            <tr>
                                <th class="min-w-[320px]">
                                    <span class="kt-table-col">
                                        <span class="kt-table-col-label">Title</span>
                                    </span>
                                </th>
                                <th class="min-w-[150px]">
                                    <span class="kt-table-col">
                                        <span class="kt-table-col-label">Type</span>
                                    </span>
                                </th>
                                <th class="min-w-[240px]">
                                    <span class="kt-table-col">
                                        <span class="kt-table-col-label">Author</span>
                                    </span>
                                </th>
                                <th class="min-w-[140px]">
                                    <span class="kt-table-col">
                                        <span class="kt-table-col-label">Status</span>
                                    </span>
                                </th>
                                <th class="min-w-[170px]">
                                    <span class="kt-table-col">
                                        <span class="kt-table-col-label">Updated</span>
                                    </span>
                                </th>
                                <th class="w-[180px] text-end">
                                    <span class="kt-table-col">
                                        <span class="kt-table-col-label">Actions</span>
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($posts as $post)
                                <tr>
                                    <td>
                                        <div class="flex flex-col gap-0.5">
                                            <a class="leading-none font-medium text-sm text-mono hover:text-primary" href="{{ route('cms-core.posts.show', $post) }}">
                                                {{ $post->title }}
                                            </a>
                                            <span class="text-xs text-secondary-foreground">{{ $post->slug }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="kt-badge kt-badge-outline">{{ $post->postType?->name ?: 'Unknown' }}</span>
                                    </td>
                                    <td class="text-foreground">{{ $post->author?->displayName() ?: 'Unknown Author' }}</td>
                                    <td>
                                        @php
                                            $statusClass = match ($post->status) {
                                                'published' => 'kt-badge-success kt-badge-outline',
                                                'scheduled' => 'kt-badge-warning kt-badge-outline',
                                                'archived' => 'kt-badge-danger kt-badge-outline',
                                                default => 'kt-badge-outline',
                                            };
                                        @endphp
                                        <span class="kt-badge {{ $statusClass }}">{{ ucfirst($post->status) }}</span>
                                    </td>
                                    <td class="text-secondary-foreground">{{ $post->updated_at?->diffForHumans() }}</td>
                                    <td class="text-end">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <a class="kt-btn kt-btn-sm kt-btn-outline" href="{{ route('cms-core.posts.show', $post) }}">View</a>
                                            @can('cms.posts.update')
                                                <a class="kt-btn kt-btn-sm kt-btn-outline" href="{{ route('cms-core.posts.edit', $post) }}">Edit</a>
                                            @endcan
                                            @can('cms.posts.delete')
                                                <form action="{{ route('cms-core.posts.destroy', $post) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="kt-btn kt-btn-sm kt-btn-danger" onclick="return confirm('Delete this post?');" type="submit">Delete</button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-8 text-center text-muted-foreground" colspan="6">No posts found for this filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $posts->links() }}</div>
            </div>
        </div>
    </section>
@endsection
