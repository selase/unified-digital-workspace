@extends('layouts.metronic.app')

@section('title', 'Forums Moderation')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Forums</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Moderation Queue</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Flagged discussions, lock state, and recent moderator actions.</p>
                </div>
                <a href="{{ route('forums.hub') }}" class="kt-btn kt-btn-outline">Back to Hub</a>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-border bg-background p-5">
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Flagged Threads</p>
                <p class="mt-2 text-2xl font-semibold text-foreground">{{ $overview['flagged_threads'] }}</p>
            </div>
            <div class="rounded-xl border border-border bg-background p-5">
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Locked Threads</p>
                <p class="mt-2 text-2xl font-semibold text-foreground">{{ $overview['locked_threads'] }}</p>
            </div>
            <div class="rounded-xl border border-border bg-background p-5">
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Pinned Threads</p>
                <p class="mt-2 text-2xl font-semibold text-foreground">{{ $overview['pinned_threads'] }}</p>
            </div>
            <div class="rounded-xl border border-border bg-background p-5">
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Actions (24h)</p>
                <p class="mt-2 text-2xl font-semibold text-foreground">{{ $overview['actions_last_24h'] }}</p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-12">
            <div class="rounded-xl border border-border bg-background p-6 xl:col-span-8">
                <h2 class="text-lg font-semibold text-foreground">Flagged Threads</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="kt-table">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th>Thread</th>
                                <th>Channel</th>
                                <th>Posts</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @forelse($flaggedThreads as $thread)
                                <tr>
                                    <td class="font-medium">{{ $thread->title }}</td>
                                    <td>{{ $thread->channel?->name ?: 'Unknown Channel' }}</td>
                                    <td>{{ $thread->posts_count }}</td>
                                    <td>{{ $thread->updated_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-8 text-center text-muted-foreground" colspan="4">No flagged threads.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $flaggedThreads->links() }}</div>
            </div>

            <div class="rounded-xl border border-border bg-background p-6 xl:col-span-4">
                <h2 class="text-lg font-semibold text-foreground">Recent Actions</h2>
                <div class="mt-4 space-y-3">
                    @forelse($latestLogs as $log)
                        <div class="rounded-lg border border-border p-3">
                            <div class="flex items-center justify-between gap-3">
                                <span class="font-medium capitalize text-foreground">{{ $log->action }}</span>
                                <span class="text-xs text-muted-foreground">{{ $log->created_at?->diffForHumans() }}</span>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">{{ $log->thread?->title ?: 'No thread linked' }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ $log->reason ?: 'No reason provided.' }}</p>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-border p-6 text-center text-sm text-muted-foreground">No moderation logs yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
@endsection
