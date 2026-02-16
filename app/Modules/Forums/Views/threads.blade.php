@extends('layouts.metronic.app')

@section('title', 'Forums Threads')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Forums</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Thread Queue</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Live discussion threads with status and response depth.</p>
                </div>
                <a href="{{ route('forums.hub') }}" class="kt-btn kt-btn-outline">Back to Hub</a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="overflow-x-auto">
                <table class="kt-table">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>Title</th>
                            <th>Channel</th>
                            <th>Status</th>
                            <th>Posts</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-foreground">
                        @forelse($threads as $thread)
                            <tr>
                                <td>
                                    <p class="font-medium">{{ $thread->title }}</p>
                                    <p class="text-xs text-muted-foreground">{{ $thread->slug }}</p>
                                </td>
                                <td>{{ $thread->channel?->name ?: 'Unknown Channel' }}</td>
                                <td><span class="kt-badge kt-badge-outline capitalize">{{ $thread->status }}</span></td>
                                <td>{{ $thread->posts_count }}</td>
                                <td>{{ $thread->updated_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-8 text-center text-muted-foreground" colspan="5">No threads found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $threads->links() }}</div>
        </div>
    </section>
@endsection
