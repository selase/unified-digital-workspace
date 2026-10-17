@extends('layouts.metronic.app')

@section('title', 'Forums Moderation Console')

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="Moderation Console & Audit"
        description="Institutional discussion governance, flagged topic adjudication, and action audit trail."
    >
        <x-slot:actions>
            <a href="{{ route('forums.hub') }}" class="kt-btn kt-btn-outline">
                Back to Hub
            </a>
            <a href="{{ route('forums.threads.index') }}" class="kt-btn kt-btn-outline">
                Thread Queue
            </a>
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

    {{-- Overview Metrics Row --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Flagged Topics</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold {{ $overview['flagged_threads'] > 0 ? 'text-amber-500' : 'text-foreground' }}">
                    {{ $overview['flagged_threads'] }}
                </span>
                <span class="text-[11px] text-muted-foreground">Awaiting review</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Locked Threads</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-foreground">{{ $overview['locked_threads'] }}</span>
                <span class="text-[11px] text-muted-foreground">Replies closed</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Pinned Notices</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-primary">{{ $overview['pinned_threads'] }}</span>
                <span class="text-[11px] text-muted-foreground">Channel headers</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Actions Logged</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-foreground">{{ $overview['actions_last_24h'] }}</span>
                <span class="text-[11px] text-muted-foreground">Past 24 hours</span>
            </div>
        </div>
    </div>

    {{-- Main Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Left 8 cols: Flagged Threads Queue --}}
        <div class="lg:col-span-8 flex flex-col gap-6">
            <x-metronic.card title="Flagged Discussions Queue" subtitle="Topics reported or flagged by policy triggers">
                <div class="kt-scrollable-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground border-b border-border bg-muted/20">
                                <th class="py-3 px-4 font-semibold text-left">Thread Title</th>
                                <th class="py-3 px-4 font-semibold text-left">Channel</th>
                                <th class="py-3 px-4 font-semibold text-center">Replies</th>
                                <th class="py-3 px-4 font-semibold text-left">Flagged Time</th>
                                <th class="py-3 px-4 font-semibold text-right">Moderator Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            @forelse($flaggedThreads as $thread)
                                <tr class="hover:bg-muted/10 transition-colors">
                                    <td class="py-3.5 px-4 font-medium text-foreground">
                                        <a href="{{ route('forums.threads.show', $thread) }}" class="font-semibold text-foreground hover:text-primary transition-colors block">
                                            {{ $thread->title }}
                                        </a>
                                    </td>
                                    <td class="py-3.5 px-4 text-xs">
                                        #{{ $thread->channel?->name ?: 'General' }}
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-muted text-foreground">
                                            {{ $thread->posts_count }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-xs text-muted-foreground">
                                        {{ $thread->updated_at?->diffForHumans() }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <a href="{{ route('forums.threads.show', $thread) }}" class="kt-btn kt-btn-sm kt-btn-outline text-xs">
                                                Review
                                            </a>
                                            <form action="{{ route('forums.threads.flag', $thread) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="kt-btn kt-btn-sm kt-btn-ghost text-xs text-emerald-600 hover:bg-emerald-500/10">
                                                    Resolve
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-12 text-center text-muted-foreground">
                                        <p class="text-sm">Queue is clear! Zero flagged discussion threads require intervention.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 pt-4 border-t border-border">
                    {{ $flaggedThreads->links() }}
                </div>
            </x-metronic.card>
        </div>

        {{-- Right 4 cols: Audit Log --}}
        <div class="lg:col-span-4 flex flex-col gap-6">
            <x-metronic.card title="Recent Moderation Actions" subtitle="Institutional audit trail of administrative interventions">
                <div class="divide-y divide-border text-xs">
                    @forelse($latestLogs as $log)
                        <div class="py-3 first:pt-0 last:pb-0">
                            <div class="flex items-center justify-between font-semibold text-foreground">
                                <span class="capitalize">{{ str_replace('_', ' ', $log->action) }}</span>
                                <span class="text-2xs text-muted-foreground">{{ $log->created_at?->diffForHumans() }}</span>
                            </div>
                            <div class="mt-1 text-muted-foreground">
                                Topic: <span class="font-medium text-foreground">{{ $log->thread?->title ?: 'Deleted Thread' }}</span>
                            </div>
                            <div class="mt-0.5 text-2xs text-muted-foreground">
                                Moderator: {{ $log->moderator ? $log->moderator->first_name . ' ' . $log->moderator->last_name : 'System' }}
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-muted-foreground">
                            No moderation actions recorded yet.
                        </div>
                    @endforelse
                </div>
            </x-metronic.card>
        </div>
    </div>
</div>
@endsection
