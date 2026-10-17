@extends('layouts.metronic.app')

@section('title', 'Direct Messages & Communications')

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="Direct & Scoped Messaging"
        description="Confidential institutional peer-to-peer communications with delivery verification and read tracking."
    >
        <x-slot:actions>
            <a href="{{ route('forums.hub') }}" class="kt-btn kt-btn-outline">
                Back to Hub
            </a>
            @can('forums.messages.send')
                <a href="{{ route('forums.messages.create') }}" class="kt-btn kt-btn-primary flex items-center gap-1.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Compose Message
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

    <x-metronic.card title="Message Inboxes ({{ $messages->total() }})" subtitle="{{ $unreadCount }} unread messages awaiting your review">
        <div class="mb-5 pb-4 border-b border-border">
            <form method="GET" action="{{ route('forums.messages.index') }}" class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[240px]">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search message subject or content…"
                        class="kt-input w-full"
                    />
                </div>
                <button type="submit" class="kt-btn kt-btn-outline">Search</button>
                @if($search)
                    <a href="{{ route('forums.messages.index') }}" class="kt-btn kt-btn-ghost text-xs text-muted-foreground">Reset</a>
                @endif
            </form>
        </div>

        <div class="kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr class="text-xs uppercase text-muted-foreground border-b border-border bg-muted/20">
                        <th class="py-3 px-4 font-semibold text-left">Subject & Preview</th>
                        <th class="py-3 px-4 font-semibold text-left">From</th>
                        <th class="py-3 px-4 font-semibold text-center">Recipients</th>
                        <th class="py-3 px-4 font-semibold text-left">Date</th>
                        <th class="py-3 px-4 font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border text-sm">
                    @forelse($messages as $msg)
                        <tr class="hover:bg-muted/10 transition-colors">
                            <td class="py-3.5 px-4 font-medium text-foreground">
                                <a href="{{ route('forums.messages.show', $msg) }}" class="font-semibold text-foreground hover:text-primary transition-colors block">
                                    {{ $msg->subject }}
                                </a>
                                <p class="text-xs text-muted-foreground line-clamp-1 mt-0.5">{{ Str::limit($msg->body, 80) }}</p>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-2">
                                    <div class="h-7 w-7 rounded-full bg-primary/10 text-primary font-bold flex items-center justify-center text-xs shrink-0">
                                        {{ strtoupper(substr($msg->sender?->first_name ?: 'S', 0, 1)) }}
                                    </div>
                                    <span class="text-xs font-medium">
                                        {{ $msg->sender ? $msg->sender->first_name . ' ' . $msg->sender->last_name : 'Staff Member' }}
                                    </span>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-muted text-foreground">
                                    {{ $msg->recipients_count }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-muted-foreground">
                                {{ $msg->created_at?->diffForHumans() }}
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <a href="{{ route('forums.messages.show', $msg) }}" class="kt-btn kt-btn-sm kt-btn-outline text-xs">
                                    Read &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-muted-foreground">
                                <p class="text-sm">No direct messages logged in your inbox.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 pt-4 border-t border-border">
            {{ $messages->links() }}
        </div>
    </x-metronic.card>
</div>
@endsection
