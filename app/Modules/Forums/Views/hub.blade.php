@extends('layouts.metronic.app')

@section('title', 'Forums & Collaboration Hub')

@section('content')
@php
    $channels = $channels ?? collect();
    $latestThreads = $latestThreads ?? collect();
    $pinnedThreads = $pinnedThreads ?? collect();
    $flaggedThreads = $flaggedThreads ?? collect();
    $latestModerationLogs = $latestModerationLogs ?? collect();
    $metrics = $metrics ?? [
        'channels' => $channels->count(),
        'threads' => $latestThreads->count(),
        'posts' => 0,
        'flagged' => $flaggedThreads->count(),
        'unread_messages' => 0,
    ];
@endphp
<div class="flex flex-col gap-6">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="Forums Hub"
        description="Multi-department discussion workspaces, structured channels, threaded exchanges, and secure peer messaging."
    >
        <x-slot:actions>
            <a href="{{ route('forums.channels.index') }}" class="kt-btn kt-btn-outline">
                Channels
            </a>
            <a href="{{ route('forums.threads.index') }}" class="kt-btn kt-btn-outline">
                All Discussions
            </a>
            <a href="{{ route('forums.messages.index') }}" class="kt-btn kt-btn-outline relative">
                Direct Messages
                @if(($metrics['unread_messages'] ?? 0) > 0)
                    <span class="ml-1.5 px-1.5 py-0.2 rounded-full text-2xs bg-primary text-primary-foreground font-bold">
                        {{ $metrics['unread_messages'] }}
                    </span>
                @endif
            </a>
            @can('forums.moderate')
                <a href="{{ route('forums.moderation.index') }}" class="kt-btn kt-btn-outline">
                    Moderation Queue
                </a>
            @endcan
            @can('forums.post')
                <a href="{{ route('forums.threads.create') }}" class="kt-btn kt-btn-primary flex items-center gap-1.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    New Discussion
                </a>
            @endcan
        </x-slot:actions>
    </x-metronic.page-header>

    @if(session('message'))
        <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-emerald-800 dark:text-emerald-300 text-sm flex items-center gap-2">
            <svg class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            {{ session('message') }}
        </div>
    @endif

    {{-- Top Metrics: 5 Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Channels</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-foreground">{{ $metrics['channels'] }}</span>
                <span class="text-[11px] text-muted-foreground">Workspaces</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Discussion Threads</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-primary">{{ $metrics['threads'] }}</span>
                <span class="text-[11px] text-muted-foreground">Active topics</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Contributions</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-foreground">{{ $metrics['posts'] }}</span>
                <span class="text-[11px] text-muted-foreground">Replies logged</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Moderation Queue</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold {{ $metrics['flagged'] > 0 ? 'text-amber-500' : 'text-emerald-500' }}">
                    {{ $metrics['flagged'] }}
                </span>
                <span class="text-[11px] text-muted-foreground">{{ $metrics['flagged'] > 0 ? 'Flagged items' : 'Clear' }}</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Direct Messages</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-foreground">{{ $metrics['unread_messages'] }}</span>
                <span class="text-[11px] text-muted-foreground">Unread inbox</span>
            </div>
        </div>
    </div>

    {{-- Main Workspace Split --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Left 8 cols: Pinned Notices & Active Discussions --}}
        <div class="lg:col-span-8 flex flex-col gap-6">
            @if($pinnedThreads->isNotEmpty())
                <x-metronic.card title="Pinned Institutional Notices" subtitle="Promoted discussions, operational alerts, and official updates">
                    <div class="divide-y divide-border">
                        @foreach($pinnedThreads as $pinned)
                            <div class="py-3.5 first:pt-0 last:pb-0 flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <div class="mt-0.5 h-8 w-8 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0 border border-amber-500/20">
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                        </svg>
                                    </div>
                                     <div>
                                        <a href="{{ route('forums.threads.show', data_get($pinned, 'slug', data_get($pinned, 'id', data_get($pinned, 'uuid', 1)))) }}" class="font-semibold text-foreground hover:text-primary transition-colors text-sm">
                                            {{ $pinned->title }}
                                        </a>
                                        <div class="flex items-center gap-2 mt-1 text-xs text-muted-foreground">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-muted text-foreground text-2xs font-medium">
                                                {{ data_get($pinned, 'channel.name', 'General') }}
                                            </span>
                                            <span>&bull;</span>
                                            <span>Posted by {{ data_get($pinned, 'author') ? data_get($pinned, 'author.first_name') . ' ' . data_get($pinned, 'author.last_name') : 'Staff Member' }}</span>
                                            <span>&bull;</span>
                                            <span>{{ data_get($pinned, 'updated_at')?->diffForHumans() }}</span>
                                        </div>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-primary/10 text-primary shrink-0">
                                    {{ data_get($pinned, 'posts_count', 0) }} {{ Str::plural('post', (int) data_get($pinned, 'posts_count', 0)) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </x-metronic.card>
            @endif

            <x-metronic.card title="Recent Discussion Threads" subtitle="Latest peer interactions and team collaboration topics">
                <div class="divide-y divide-border">
                    @forelse($latestThreads as $thread)
                        @php
                            $threadParam = data_get($thread, 'slug', data_get($thread, 'id', data_get($thread, 'uuid', 1)));
                            $threadChanParam = data_get($thread, 'channel.slug', data_get($thread, 'channel.id', data_get($thread, 'channel.uuid', 'general')));
                        @endphp
                        <div class="py-3.5 first:pt-0 last:pb-0 flex items-start justify-between gap-3 hover:bg-muted/5 transition-colors rounded-lg px-2 -mx-2">
                            <div class="flex items-start gap-3">
                                <div class="h-9 w-9 rounded-full bg-primary/10 text-primary font-bold flex items-center justify-center text-xs shrink-0 border border-primary/20">
                                    {{ strtoupper(substr((string) data_get($thread, 'author.first_name', 'S'), 0, 1) . substr((string) data_get($thread, 'author.last_name', 'T'), 0, 1)) }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('forums.threads.show', $threadParam) }}" class="font-semibold text-foreground hover:text-primary transition-colors text-sm">
                                            {{ $thread->title }}
                                        </a>
                                        @if(data_get($thread, 'status') === 'locked')
                                            <x-metronic.badge variant="secondary" size="sm">Locked</x-metronic.badge>
                                        @elseif(data_get($thread, 'status') === 'flagged')
                                            <x-metronic.badge variant="warning" size="sm">Flagged</x-metronic.badge>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2 mt-1 text-xs text-muted-foreground flex-wrap">
                                        @if(data_get($thread, 'channel'))
                                            <a href="{{ route('forums.channels.show', $threadChanParam) }}" class="text-primary hover:underline font-medium">
                                                #{{ data_get($thread, 'channel.name') }}
                                            </a>
                                        @else
                                            <span class="text-xs text-muted-foreground">#general</span>
                                        @endif
                                        <span>&bull;</span>
                                        <span>By {{ data_get($thread, 'author') ? data_get($thread, 'author.first_name') . ' ' . data_get($thread, 'author.last_name') : 'Staff Member' }}</span>
                                        <span>&bull;</span>
                                        <span>{{ data_get($thread, 'updated_at')?->diffForHumans() }}</span>
                                        @if(!empty(data_get($thread, 'tags')))
                                            <span>&bull;</span>
                                            <div class="inline-flex gap-1">
                                                @foreach(array_slice((array) data_get($thread, 'tags'), 0, 2) as $tag)
                                                    <span class="text-2xs px-1.5 py-0.2 rounded bg-muted text-muted-foreground">#{{ $tag }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-muted text-foreground">
                                    {{ data_get($thread, 'posts_count', 0) }} {{ Str::plural('reply', (int) data_get($thread, 'posts_count', 0)) }}
                                </span>
                                <a href="{{ route('forums.threads.show', $threadParam) }}" class="kt-btn kt-btn-sm kt-btn-outline text-xs">
                                    Open
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-muted-foreground text-sm">
                            No discussion threads initiated yet. Start the first topic!
                        </div>
                    @endforelse
                </div>

                <div class="mt-4 pt-4 border-t border-border flex justify-end">
                    <a href="{{ route('forums.threads.index') }}" class="text-xs font-medium text-primary hover:underline flex items-center gap-1">
                        View All Discussion Threads &rarr;
                    </a>
                </div>
            </x-metronic.card>
        </div>

        {{-- Right 4 cols: Channels Directory & Moderation Widget --}}
        <div class="lg:col-span-4 flex flex-col gap-6">
            <x-metronic.card title="Channels Directory" subtitle="Structured discussion topics">
                <div class="space-y-3">
                    @forelse($channels as $channel)
                        @php
                            $channelParam = data_get($channel, 'slug', data_get($channel, 'id', data_get($channel, 'uuid', 'general')));
                        @endphp
                        <div class="flex items-center justify-between p-3 rounded-xl border border-border bg-muted/15 hover:bg-muted/30 transition-colors">
                            <div class="flex items-center gap-2.5">
                                <div class="h-8 w-8 rounded-lg bg-primary/10 text-primary font-bold flex items-center justify-center text-xs shrink-0">
                                    #
                                </div>
                                <div>
                                    <a href="{{ route('forums.channels.show', $channelParam) }}" class="font-medium text-foreground hover:text-primary text-sm">
                                        {{ $channel->name }}
                                    </a>
                                    @if(!empty(data_get($channel, 'description')))
                                        <p class="text-xs text-muted-foreground line-clamp-1">{{ data_get($channel, 'description') }}</p>
                                    @endif
                                </div>
                            </div>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-muted text-foreground shrink-0">
                                {{ data_get($channel, 'threads_count', 0) }}
                            </span>
                        </div>
                    @empty
                        <div class="p-4 text-center text-xs text-muted-foreground">No channels established yet.</div>
                    @endforelse
                </div>

                <div class="mt-4 pt-4 border-t border-border flex items-center justify-between">
                    <a href="{{ route('forums.channels.index') }}" class="text-xs font-medium text-primary hover:underline">
                        All Channels &rarr;
                    </a>
                    @can('forums.moderate')
                        <a href="{{ route('forums.channels.create') }}" class="kt-btn kt-btn-sm kt-btn-outline text-xs">
                            + New Channel
                        </a>
                    @endcan
                </div>
            </x-metronic.card>

            <x-metronic.card title="Institutional Governance & Integrity" subtitle="Content safety & community standards">
                <div class="space-y-3 text-xs text-muted-foreground">
                    <p>
                        Institutional forums enable peer engagement, clinical and operational task coordination, and knowledge exchange.
                    </p>
                    <div class="p-3 rounded-lg bg-muted/30 border border-border space-y-1">
                        <span class="font-semibold text-foreground block">Governance Rules:</span>
                        <ul class="list-disc list-inside space-y-0.5">
                            <li>Confidential patient information prohibited</li>
                            <li>All activity logged in audit trail</li>
                            <li>Moderators can lock or flag topics</li>
                        </ul>
                    </div>
                </div>

                @can('forums.moderate')
                    <div class="mt-4 pt-4 border-t border-border">
                        <a href="{{ route('forums.moderation.index') }}" class="kt-btn kt-btn-outline w-full justify-center text-xs">
                            Open Moderation Console
                        </a>
                    </div>
                @endcan
            </x-metronic.card>
        </div>
    </div>
</div>
@endsection
