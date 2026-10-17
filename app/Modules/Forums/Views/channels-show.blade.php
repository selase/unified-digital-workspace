@extends('layouts.metronic.app')

@section('title', '#' . $channel->name . ' - Channel')

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="#{{ $channel->name }}"
        description="{{ $channel->description ?: 'Active institutional discussion channel.' }}"
    >
        <x-slot:actions>
            <a href="{{ route('forums.channels.index') }}" class="kt-btn kt-btn-outline">
                All Channels
            </a>
            @if(!$channel->is_locked)
                @can('forums.post')
                    <a href="{{ route('forums.threads.create', ['channel_id' => $channel->id]) }}" class="kt-btn kt-btn-primary flex items-center gap-1.5">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Start Discussion
                    </a>
                @endcan
            @else
                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-muted text-muted-foreground border border-border">
                    Channel Locked
                </span>
            @endif
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

    <x-metronic.card title="Discussions ({{ $threads->total() }})" subtitle="Threads published under #{{ $channel->name }}">
        <div class="mb-5 pb-4 border-b border-border">
            <form method="GET" action="{{ route('forums.channels.show', $channel) }}" class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[240px]">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search threads in this channel…"
                        class="kt-input w-full"
                    />
                </div>
                <div class="w-40">
                    <select name="status" onchange="this.form.submit()" class="kt-select w-full">
                        <option value="">All Statuses</option>
                        <option value="open" @selected($status === 'open')>Open Only</option>
                        <option value="locked" @selected($status === 'locked')>Locked Only</option>
                        <option value="flagged" @selected($status === 'flagged')>Flagged Only</option>
                    </select>
                </div>
                <button type="submit" class="kt-btn kt-btn-outline">Filter</button>
                @if($search || $status)
                    <a href="{{ route('forums.channels.show', $channel) }}" class="kt-btn kt-btn-ghost text-xs text-muted-foreground">Reset</a>
                @endif
            </form>
        </div>

        <div class="divide-y divide-border">
            @forelse($threads as $thread)
                <div class="py-4 first:pt-0 last:pb-0 flex items-start justify-between gap-4 hover:bg-muted/5 transition-colors px-2 -mx-2 rounded-lg">
                    <div class="flex items-start gap-3">
                        <div class="h-10 w-10 rounded-full bg-primary/10 text-primary font-bold flex items-center justify-center text-xs shrink-0 border border-primary/20">
                            {{ strtoupper(substr($thread->author?->first_name ?: 'A', 0, 1) . substr($thread->author?->last_name ?: 'U', 0, 1)) }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                @if($thread->pinned_at)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                        PINNED
                                    </span>
                                @endif
                                <a href="{{ route('forums.threads.show', $thread) }}" class="font-semibold text-foreground hover:text-primary transition-colors text-base">
                                    {{ $thread->title }}
                                </a>
                                @if($thread->status === 'locked')
                                    <x-metronic.badge variant="secondary" size="sm">Locked</x-metronic.badge>
                                @elseif($thread->status === 'flagged')
                                    <x-metronic.badge variant="warning" size="sm">Flagged</x-metronic.badge>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 mt-1 text-xs text-muted-foreground flex-wrap">
                                <span>Initiated by {{ $thread->author ? $thread->author->first_name . ' ' . $thread->author->last_name : 'Staff Member' }}</span>
                                <span>&bull;</span>
                                <span>Last updated {{ $thread->updated_at?->diffForHumans() }}</span>
                                @if(!empty($thread->tags))
                                    <span>&bull;</span>
                                    <div class="inline-flex gap-1">
                                        @foreach($thread->tags as $tag)
                                            <span class="text-2xs px-1.5 py-0.2 rounded bg-muted text-muted-foreground">#{{ $tag }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-muted text-foreground">
                            {{ $thread->posts_count }} {{ Str::plural('reply', $thread->posts_count) }}
                        </span>
                        <a href="{{ route('forums.threads.show', $thread) }}" class="kt-btn kt-btn-sm kt-btn-outline text-xs">
                            View Discussion
                        </a>
                    </div>
                </div>
            @empty
                <div class="py-12 text-center text-muted-foreground text-sm">
                    No discussions found in this channel. Be the first to start a topic!
                </div>
            @endforelse
        </div>

        <div class="mt-4 pt-4 border-t border-border">
            {{ $threads->links() }}
        </div>
    </x-metronic.card>
</div>
@endsection

