@extends('layouts.metronic.app')

@section('title', 'All Discussion Threads')

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="Discussion Threads"
        description="Institutional knowledge exchange, collaborative workflows, and multi-team discussions across all channels."
    >
        <x-slot:actions>
            <a href="{{ route('forums.hub') }}" class="kt-btn kt-btn-outline">
                Back to Hub
            </a>
            <a href="{{ route('forums.channels.index') }}" class="kt-btn kt-btn-outline">
                Channels
            </a>
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

    <x-metronic.card title="Thread Registry ({{ $threads->total() }})" subtitle="Complete institutional conversation history and active exchanges">
        <div class="mb-5 pb-4 border-b border-border">
            <form method="GET" action="{{ route('forums.threads.index') }}" class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[220px]">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search thread title or keyword…"
                        class="kt-input w-full"
                    />
                </div>
                <div class="w-44">
                    <select name="channel" onchange="this.form.submit()" class="kt-select w-full">
                        <option value="">All Channels</option>
                        @foreach($channels as $c)
                            <option value="{{ $c->slug }}" @selected($channelSlug === $c->slug)>#{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-36">
                    <select name="status" onchange="this.form.submit()" class="kt-select w-full">
                        <option value="">All Statuses</option>
                        <option value="open" @selected($status === 'open')>Open Only</option>
                        <option value="locked" @selected($status === 'locked')>Locked Only</option>
                        <option value="flagged" @selected($status === 'flagged')>Flagged Only</option>
                    </select>
                </div>
                <button type="submit" class="kt-btn kt-btn-outline">Filter</button>
                @if($search || $status || $channelSlug)
                    <a href="{{ route('forums.threads.index') }}" class="kt-btn kt-btn-ghost text-xs text-muted-foreground">Reset</a>
                @endif
            </form>
        </div>

        <div class="kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr class="text-xs uppercase text-muted-foreground border-b border-border bg-muted/20">
                        <th class="py-3 px-4 font-semibold text-left">Discussion Topic</th>
                        <th class="py-3 px-4 font-semibold text-left">Channel</th>
                        <th class="py-3 px-4 font-semibold text-center">Replies</th>
                        <th class="py-3 px-4 font-semibold text-left">Status</th>
                        <th class="py-3 px-4 font-semibold text-left">Last Activity</th>
                        <th class="py-3 px-4 font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border text-sm">
                    @forelse($threads as $thread)
                        <tr class="hover:bg-muted/10 transition-colors">
                            <td class="py-3.5 px-4">
                                <div class="flex items-start gap-3">
                                    <div class="h-8 w-8 rounded-full bg-primary/10 text-primary font-bold flex items-center justify-center text-xs shrink-0 border border-primary/20">
                                        {{ strtoupper(substr($thread->author?->first_name ?: 'A', 0, 1) . substr($thread->author?->last_name ?: 'U', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            @if($thread->pinned_at)
                                                <span class="inline-flex items-center px-1.5 py-0.2 rounded text-2xs font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                                    PIN
                                                </span>
                                            @endif
                                            <a href="{{ route('forums.threads.show', $thread) }}" class="font-semibold text-foreground hover:text-primary transition-colors">
                                                {{ $thread->title }}
                                            </a>
                                        </div>
                                        <p class="text-xs text-muted-foreground mt-0.5">
                                            By {{ $thread->author ? $thread->author->first_name . ' ' . $thread->author->last_name : 'Staff Member' }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                @if($thread->channel)
                                    <a href="{{ route('forums.channels.show', $thread->channel) }}" class="text-primary hover:underline text-xs font-medium">
                                        #{{ $thread->channel->name }}
                                    </a>
                                @else
                                    <span class="text-xs text-muted-foreground italic">—</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-muted text-foreground">
                                    {{ $thread->posts_count }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                @if($thread->status === 'open')
                                    <x-metronic.badge variant="success" size="sm">Open</x-metronic.badge>
                                @elseif($thread->status === 'locked')
                                    <x-metronic.badge variant="secondary" size="sm">Locked</x-metronic.badge>
                                @elseif($thread->status === 'flagged')
                                    <x-metronic.badge variant="warning" size="sm">Flagged</x-metronic.badge>
                                @else
                                    <x-metronic.badge variant="secondary" size="sm">{{ ucfirst($thread->status) }}</x-metronic.badge>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-xs text-muted-foreground">
                                {{ $thread->updated_at?->diffForHumans() }}
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <a href="{{ route('forums.threads.show', $thread) }}" class="kt-btn kt-btn-sm kt-btn-outline text-xs">
                                    Open
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-muted-foreground">
                                <p class="text-sm">No discussion threads found matching your filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 pt-4 border-t border-border">
            {{ $threads->links() }}
        </div>
    </x-metronic.card>
</div>
@endsection
