@extends('layouts.metronic.app')

@section('title', 'Forums Channels Directory')

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="Discussion Channels"
        description="Structured communication spaces organized by department, clinical domain, and institutional collaboration streams."
    >
        <x-slot:actions>
            <a href="{{ route('forums.hub') }}" class="kt-btn kt-btn-outline">
                Back to Hub
            </a>
            <a href="{{ route('forums.threads.index') }}" class="kt-btn kt-btn-outline">
                All Discussions
            </a>
            @can('forums.moderate')
                <a href="{{ route('forums.channels.create') }}" class="kt-btn kt-btn-primary flex items-center gap-1.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Create Channel
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

    <x-metronic.card title="Channel Registry ({{ $channels->total() }})" subtitle="Browse and participate in active institutional communication streams">
        <div class="mb-5 pb-4 border-b border-border">
            <form method="GET" action="{{ route('forums.channels.index') }}" class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[240px]">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search channel name or topic…"
                        class="kt-input w-full"
                    />
                </div>
                <button type="submit" class="kt-btn kt-btn-outline">Filter</button>
                @if($search)
                    <a href="{{ route('forums.channels.index') }}" class="kt-btn kt-btn-ghost text-xs text-muted-foreground">Reset</a>
                @endif
            </form>
        </div>

        <div class="kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr class="text-xs uppercase text-muted-foreground border-b border-border bg-muted/20">
                        <th class="py-3 px-4 font-semibold text-left">Channel Name & Description</th>
                        <th class="py-3 px-4 font-semibold text-left">Slug</th>
                        <th class="py-3 px-4 font-semibold text-center">Threads</th>
                        <th class="py-3 px-4 font-semibold text-center">Flagged</th>
                        <th class="py-3 px-4 font-semibold text-left">Status</th>
                        <th class="py-3 px-4 font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border text-sm">
                    @forelse($channels as $channel)
                        <tr class="hover:bg-muted/10 transition-colors">
                            <td class="py-3.5 px-4 font-medium text-foreground">
                                <div class="flex items-center gap-2.5">
                                    <div class="h-8 w-8 rounded-lg bg-primary/10 text-primary font-bold flex items-center justify-center text-xs shrink-0 border border-primary/20">
                                        #
                                    </div>
                                    <div>
                                        <a href="{{ route('forums.channels.show', $channel) }}" class="font-semibold text-foreground hover:text-primary transition-colors">
                                            {{ $channel->name }}
                                        </a>
                                        <p class="text-xs text-muted-foreground">{{ $channel->description ?: 'No formal description provided.' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="font-mono text-xs px-2 py-0.5 rounded bg-muted text-foreground border border-border">
                                    {{ $channel->slug }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-muted text-foreground">
                                    {{ $channel->threads_count }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                @if($channel->flagged_threads_count > 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                        {{ $channel->flagged_threads_count }}
                                    </span>
                                @else
                                    <span class="text-xs text-muted-foreground">0</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <x-metronic.badge :variant="$channel->is_locked ? 'secondary' : 'success'" size="sm">
                                    {{ $channel->is_locked ? 'Locked' : 'Open' }}
                                </x-metronic.badge>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <a href="{{ route('forums.channels.show', $channel) }}" class="kt-btn kt-btn-sm kt-btn-outline text-xs">
                                    Browse &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-muted-foreground">
                                <p class="text-sm">No channels found matching your search query.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 pt-4 border-t border-border">
            {{ $channels->links() }}
        </div>
    </x-metronic.card>
</div>
@endsection
