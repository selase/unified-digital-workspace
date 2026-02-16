@extends('layouts.metronic.app')

@section('title', 'CMS Posts')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">CMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Post Library</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Tenant content entries by type, author, and publication status.</p>
                </div>
                <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.index') }}">Back to Hub</a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="overflow-x-auto">
                <table class="kt-table">
                    <thead>
                    <tr class="text-xs uppercase text-muted-foreground">
                        <th>Title</th>
                        <th>Type</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th>Updated</th>
                    </tr>
                    </thead>
                    <tbody class="text-sm text-foreground">
                    @forelse($posts as $post)
                        <tr>
                            <td>
                                <p class="font-medium">{{ $post->title }}</p>
                                <p class="text-xs text-muted-foreground">{{ $post->slug }}</p>
                            </td>
                            <td>{{ $post->postType?->name ?: 'Unknown Type' }}</td>
                            <td>{{ $post->author?->displayName() ?: 'Unknown Author' }}</td>
                            <td><span class="kt-badge kt-badge-outline">{{ ucfirst($post->status) }}</span></td>
                            <td>{{ $post->updated_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="py-8 text-center text-muted-foreground" colspan="5">No posts found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $posts->links() }}</div>
        </div>
    </section>
@endsection
