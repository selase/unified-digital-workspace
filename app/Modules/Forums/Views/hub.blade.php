@extends('layouts.metronic.app')

@section('title', 'Forums Hub')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Forums</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Forums Hub</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Channels, live discussions, and moderation overview.</p>
                </div>
                <a href="{{ route('api.forums.v1.channels.index') }}" class="kt-btn kt-btn-outline">Open Channels API</a>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-12">
            <div class="rounded-xl border border-border bg-background p-6 xl:col-span-4">
                <h2 class="text-lg font-semibold text-foreground">Top Channels</h2>
                <div class="mt-4 space-y-3">
                    @forelse($channels as $channel)
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-border p-3">
                            <div>
                                <p class="font-medium text-foreground">{{ $channel->name }}</p>
                                <p class="text-xs text-muted-foreground">{{ $channel->slug }}</p>
                            </div>
                            <span class="kt-badge kt-badge-primary">{{ $channel->threads_count }} threads</span>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-border p-6 text-center text-sm text-muted-foreground">No channels yet.</div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-border bg-background p-6 xl:col-span-8">
                <h2 class="text-lg font-semibold text-foreground">Latest Threads</h2>
                <div class="mt-4 space-y-3">
                    @forelse($latestThreads as $thread)
                        <div class="rounded-lg border border-border p-3">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-medium text-foreground">{{ $thread->title }}</p>
                                <span class="kt-badge kt-badge-outline">{{ $thread->status }}</span>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Channel: {{ $thread->channel?->name ?? 'N/A' }} · Updated {{ $thread->updated_at?->diffForHumans() }}
                            </p>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-border p-6 text-center text-sm text-muted-foreground">No threads yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-xl border border-border bg-background p-6">
                <h2 class="text-lg font-semibold text-foreground">Flagged Threads</h2>
                <div class="mt-4 space-y-3">
                    @forelse($flaggedThreads as $thread)
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-border p-3">
                            <span class="font-medium text-foreground">{{ $thread->title }}</span>
                            <span class="kt-badge kt-badge-destructive">Flagged</span>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-border p-6 text-center text-sm text-muted-foreground">No flagged threads.</div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-border bg-background p-6">
                <h2 class="text-lg font-semibold text-foreground">Moderation Activity</h2>
                <div class="mt-4 space-y-3">
                    @forelse($latestModerationLogs as $log)
                        <div class="rounded-lg border border-border p-3">
                            <div class="flex items-center justify-between gap-3">
                                <span class="font-medium capitalize text-foreground">{{ $log->action }}</span>
                                <span class="text-xs text-muted-foreground">{{ $log->created_at?->diffForHumans() }}</span>
                            </div>
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
