@extends('layouts.metronic.app')

@section('title', 'Institutional Announcements Feed')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-6 rounded-xl bg-gradient-to-r from-primary/10 via-primary/5 to-transparent border border-primary/20">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('intranet.index') }}" class="text-xs text-primary hover:underline flex items-center gap-1">
                    &larr; Back to Intranet Hub
                </a>
                <span class="text-xs text-muted-foreground">&bull;</span>
                <span class="kt-badge kt-badge-xs kt-badge-primary uppercase">Official Notices</span>
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight text-foreground">Announcements Feed</h1>
            <p class="text-sm text-muted-foreground mt-0.5">
                Aggregated feed of official notices, leadership advisories, and pinned board announcements across your accessible organizational workspaces.
            </p>
        </div>
    </div>

    {{-- Search Bar --}}
    <div class="p-4 rounded-xl border border-border bg-card">
        <form method="GET" action="{{ route('intranet.announcements') }}" class="flex items-center gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search announcements..." class="kt-input kt-input-sm text-xs w-full sm:w-80">
            <button type="submit" class="kt-btn kt-btn-sm kt-btn-outline text-xs">Search</button>
            @if(request('search'))
                <a href="{{ route('intranet.announcements') }}" class="text-xs text-muted-foreground hover:underline">Clear</a>
            @endif
        </form>
    </div>

    {{-- Announcements Feed --}}
    <div class="space-y-4">
        @forelse($announcements as $item)
            <div class="p-5 rounded-xl border border-border bg-card space-y-2">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-border pb-2.5">
                    <div class="flex items-center gap-2">
                        <span class="kt-badge kt-badge-xs kt-badge-primary">Notice</span>
                        @if($item->is_pinned)
                            <span class="kt-badge kt-badge-xs kt-badge-warning">Pinned</span>
                        @endif
                        <h3 class="text-base font-bold text-foreground">{{ $item->title }}</h3>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                        @if($item->board)
                            <a href="{{ route('intranet.boards.show', $item->board) }}" class="kt-badge kt-badge-xs kt-badge-outline hover:border-primary transition-colors">
                                Board: {{ $item->board->title }}
                            </a>
                        @endif
                        <span>{{ $item->created_at?->format('d/m/Y H:i') }}</span>
                    </div>
                </div>

                <div class="text-sm text-foreground leading-relaxed whitespace-pre-line py-1">
                    {{ $item->content }}
                </div>

                <div class="text-xs text-muted-foreground pt-2 border-t border-border flex items-center justify-between">
                    <span>Issued by {{ $item->author?->first_name }} {{ $item->author?->last_name }}</span>
                    @if($item->board)
                        <a href="{{ route('intranet.boards.show', $item->board) }}" class="font-semibold text-primary hover:underline text-xs">
                            View on Board &rarr;
                        </a>
                    @endif
                </div>
            </div>
        @empty
            <div class="p-12 text-center rounded-xl border border-dashed border-border bg-card">
                <i class="ki-filled ki-notification text-3xl text-muted-foreground mb-2"></i>
                <h4 class="text-sm font-bold text-foreground">No official announcements</h4>
                <p class="text-xs text-muted-foreground mt-1">There are no notices published to your accessible message boards.</p>
            </div>
        @endforelse

        @if($announcements->hasPages())
            <div class="pt-4">
                {{ $announcements->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

