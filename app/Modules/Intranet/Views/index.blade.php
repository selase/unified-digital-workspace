@extends('layouts.metronic.app')

@section('title', 'Institutional Intranet Portal')

@section('content')
<div class="space-y-6">
    {{-- Banner / Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-6 rounded-xl bg-gradient-to-r from-primary/10 via-primary/5 to-transparent border border-primary/20">
        <div>
            <span class="kt-badge kt-badge-primary text-xs uppercase font-bold tracking-wider">Internal Workspace</span>
            <h1 class="text-2xl font-extrabold tracking-tight text-foreground mt-1">Institutional Intranet Portal</h1>
            <p class="text-sm text-muted-foreground mt-0.5">
                Internal news &amp; leadership directives, upcoming institutional calendar, staff message boards &amp; quick access to ISO controlled policies.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('hrms-core.employees.index') }}" class="kt-btn kt-btn-outline kt-btn-sm">
                <i class="ki-filled ki-people text-xs"></i>
                Staff Directory
            </a>
            <a href="{{ route('document-management.documents.index') }}" class="kt-btn kt-btn-primary kt-btn-sm">
                <i class="ki-filled ki-document text-xs"></i>
                ISO Policies Library
            </a>
        </div>
    </div>

    @if(session('message'))
        <div class="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-sm flex items-center gap-2">
            <i class="ki-filled ki-check-circle text-base"></i>
            {{ session('message') }}
        </div>
    @endif

    {{-- Main Grid: 2/3 Content (Internal News + Message Boards), 1/3 Sidebar (Upcoming Events + Controlled Policies) --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            {{-- Section 1: Internal News & Leadership Directives (CMS Scoped) --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <div class="flex items-center gap-2">
                        <i class="ki-filled ki-notification-status text-primary text-base"></i>
                        <h3 class="kt-card-title text-base font-bold">Internal News &amp; Leadership Directives</h3>
                    </div>
                    <span class="kt-badge kt-badge-xs kt-badge-outline">CMS Internal Scope</span>
                </div>
                <div class="kt-card-body p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @forelse($internalPosts as $post)
                            <div class="p-4 rounded-lg border border-border bg-card space-y-3 flex flex-col justify-between">
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="kt-badge kt-badge-xs kt-badge-primary uppercase">{{ $post->audience }}</span>
                                        <span class="text-[11px] text-muted-foreground">{{ $post->published_at?->format('d/m/Y') }}</span>
                                    </div>
                                    <h4 class="text-sm font-bold text-foreground line-clamp-2">{{ $post->title }}</h4>
                                    <p class="text-xs text-muted-foreground line-clamp-3">{{ $post->excerpt ?? strip_tags($post->body) }}</p>
                                </div>
                                <div class="text-[11px] text-muted-foreground pt-2 border-t border-border flex items-center justify-between">
                                    <span>By {{ $post->author?->first_name }} {{ $post->author?->last_name }}</span>
                                    <span class="font-semibold text-primary">Read Directive &rarr;</span>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-2 text-center py-8 text-sm text-muted-foreground">
                                No internal news or leadership directives published yet.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Section 2: Interactive Message Boards & Community Posts --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <div class="flex items-center gap-2">
                        <i class="ki-filled ki-messages text-primary text-base"></i>
                        <h3 class="kt-card-title text-base font-bold">Institutional Message Boards &amp; Notice Board</h3>
                    </div>
                </div>
                <div class="kt-card-body p-6 space-y-6">
                    @forelse($messageBoards as $board)
                        <div class="space-y-3 pb-6 border-b border-border last:border-b-0 last:pb-0">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-sm font-bold text-foreground flex items-center gap-2">
                                        {{ $board->title }}
                                        @if($board->is_pinned)
                                            <span class="kt-badge kt-badge-xs kt-badge-warning">Pinned</span>
                                        @endif
                                    </h4>
                                    <p class="text-xs text-muted-foreground">{{ $board->description }}</p>
                                </div>
                                <details class="relative inline-block text-start">
                                    <summary class="kt-btn kt-btn-xs kt-btn-outline cursor-pointer list-none">+ Post Message</summary>
                                    <div class="absolute right-0 mt-1 w-80 p-4 bg-card rounded-lg shadow-xl border border-border z-30 space-y-2">
                                        <div class="text-xs font-semibold text-foreground">Post to {{ $board->title }}</div>
                                        <form action="{{ route('intranet.boards.posts.store', $board) }}" method="POST" class="space-y-2">
                                            @csrf
                                            <input type="text" name="title" class="kt-input kt-input-sm text-xs" placeholder="Subject / Headline" required>
                                            <textarea name="content" class="kt-input kt-input-sm text-xs" rows="3" placeholder="Message content..." required></textarea>
                                            <div class="flex items-center gap-2">
                                                <input type="checkbox" name="is_announcement" value="1" class="kt-checkbox">
                                                <span class="text-xs text-muted-foreground">Highlight as Official Notice</span>
                                            </div>
                                            <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary w-full justify-center">Post to Board</button>
                                        </form>
                                    </div>
                                </details>
                            </div>

                            <div class="space-y-2">
                                @forelse($board->posts as $post)
                                    <div class="p-3 rounded-lg bg-muted/20 border border-border text-xs space-y-1">
                                        <div class="flex items-center justify-between font-semibold">
                                            <span class="text-foreground">{{ $post->title }}</span>
                                            <span class="text-[10px] text-muted-foreground">{{ $post->created_at?->format('d/m/Y H:i') }}</span>
                                        </div>
                                        <p class="text-muted-foreground">{{ $post->content }}</p>
                                        <div class="text-[11px] text-primary/80 pt-1">
                                            Posted by {{ $post->author?->first_name }} {{ $post->author?->last_name }}
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-xs text-muted-foreground py-2 italic">No messages on this board yet.</div>
                                @endforelse
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-sm text-muted-foreground">No message boards initialized.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Sidebar: Upcoming Events + Controlled Policies Quick Access --}}
        <div class="space-y-6">
            {{-- Upcoming Events --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title text-sm font-bold flex items-center gap-2">
                        <i class="ki-filled ki-calendar text-primary text-base"></i>
                        Upcoming Events
                    </h3>
                    <details class="relative inline-block text-start">
                        <summary class="kt-btn kt-btn-xs kt-btn-outline cursor-pointer list-none">+ Schedule</summary>
                        <div class="absolute right-0 mt-1 w-72 p-4 bg-card rounded-lg shadow-xl border border-border z-30 space-y-2">
                            <div class="text-xs font-semibold text-foreground">Schedule Institutional Event</div>
                            <form action="{{ route('intranet.events.store') }}" method="POST" class="space-y-2">
                                @csrf
                                <input type="text" name="title" class="kt-input kt-input-sm text-xs" placeholder="Event Title" required>
                                <input type="date" name="event_date" class="kt-input kt-input-sm text-xs" required>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="time" name="start_time" class="kt-input kt-input-sm text-xs" placeholder="Start">
                                    <input type="time" name="end_time" class="kt-input kt-input-sm text-xs" placeholder="End">
                                </div>
                                <input type="text" name="location" class="kt-input kt-input-sm text-xs" placeholder="Location / Room" required>
                                <select name="category" class="kt-select kt-select-sm text-xs" required>
                                    <option value="all_hands">All-Hands Assembly</option>
                                    <option value="training">Staff Training &amp; CME</option>
                                    <option value="board_meeting">Executive Board Meeting</option>
                                    <option value="departmental">Departmental Session</option>
                                    <option value="social">Institutional Social</option>
                                </select>
                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary w-full justify-center">Save Event</button>
                            </form>
                        </div>
                    </details>
                </div>
                <div class="kt-card-body p-5 space-y-3">
                    @forelse($upcomingEvents as $event)
                        <div class="p-3 rounded-lg border border-border bg-card text-xs space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-foreground">{{ $event->title }}</span>
                                <span class="kt-badge kt-badge-xs kt-badge-outline uppercase">{{ str_replace('_', ' ', $event->category) }}</span>
                            </div>
                            <div class="text-[11px] text-primary font-semibold flex items-center gap-1">
                                <i class="ki-filled ki-time text-xs"></i>
                                {{ $event->event_date?->format('d/m/Y') }} @if($event->start_time) &bull; {{ substr($event->start_time, 0, 5) }} @endif
                            </div>
                            <div class="text-[11px] text-muted-foreground flex items-center gap-1">
                                <i class="ki-filled ki-geolocation text-xs"></i>
                                {{ $event->location }}
                            </div>
                        </div>
                    @empty
                        <div class="text-xs text-muted-foreground text-center py-4">No upcoming events scheduled.</div>
                    @endforelse
                </div>
            </div>

            {{-- Controlled ISO Policies & Standard Operating Procedures (SOPs) --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title text-sm font-bold flex items-center gap-2">
                        <i class="ki-filled ki-shield-tick text-primary text-base"></i>
                        Controlled Policies &amp; SOPs
                    </h3>
                </div>
                <div class="kt-card-body p-5 space-y-2">
                    @forelse($controlledPolicies as $doc)
                        <div class="flex items-center justify-between p-2 rounded-lg bg-muted/20 border border-border text-xs">
                            <div>
                                <div class="font-semibold text-foreground line-clamp-1">{{ $doc->title }}</div>
                                <div class="text-[10px] text-muted-foreground font-mono">{{ $doc->document_number ?? 'ISO-DOC' }}</div>
                            </div>
                            <a href="{{ route('document-management.documents.show', $doc) }}" class="kt-btn kt-btn-xs kt-btn-outline">
                                View
                            </a>
                        </div>
                    @empty
                        <div class="text-xs text-muted-foreground text-center py-4">No published policies available.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

