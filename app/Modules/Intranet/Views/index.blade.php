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
        <div class="flex items-center flex-wrap gap-2">
            <a href="{{ route('intranet.announcements') }}" class="kt-btn kt-btn-outline kt-btn-sm">
                <i class="ki-filled ki-notification text-xs"></i>
                Announcements Feed
            </a>
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
                <div class="kt-card-header flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <i class="ki-filled ki-notification-status text-primary text-base"></i>
                        <h3 class="kt-card-title text-base font-bold">Internal News &amp; Leadership Directives</h3>
                    </div>
                    <form method="GET" action="{{ route('intranet.index') }}" class="flex items-center gap-2">
                        <input type="text" name="news_search" value="{{ request('news_search') }}" placeholder="Search news..." class="kt-input kt-input-xs text-xs w-40">
                        <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline">Filter</button>
                    </form>
                </div>
                <div class="kt-card-body p-6 space-y-4">
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
                                    <span class="font-semibold text-primary">Internal Directive</span>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-2 text-center py-8 text-sm text-muted-foreground">
                                No internal news or leadership directives published yet.
                            </div>
                        @endforelse
                    </div>

                    @if($internalPosts->hasPages())
                        <div class="pt-2">
                            {{ $internalPosts->links() }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- Section 2: Interactive Message Boards & Community Posts --}}
            <div class="kt-card">
                <div class="kt-card-header flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <i class="ki-filled ki-messages text-primary text-base"></i>
                        <h3 class="kt-card-title text-base font-bold">Institutional Message Boards &amp; Notice Boards</h3>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        @if($currentUser->can('intranet.boards.manage') || $currentUser->can('intranet.manage'))
                            <details class="relative inline-block text-start">
                                <summary class="kt-btn kt-btn-xs kt-btn-primary cursor-pointer list-none">+ New Board</summary>
                                <div class="absolute right-0 mt-1 w-80 p-4 bg-card rounded-lg shadow-xl border border-border z-30 space-y-3">
                                    <div class="text-xs font-semibold text-foreground">Create Message Board</div>
                                    <form action="{{ route('intranet.boards.store') }}" method="POST" class="space-y-2">
                                        @csrf
                                        <div>
                                            <label class="text-[11px] font-medium text-muted-foreground">Title</label>
                                            <input type="text" name="title" class="kt-input kt-input-sm text-xs w-full" placeholder="Board Title" required>
                                        </div>
                                        <div>
                                            <label class="text-[11px] font-medium text-muted-foreground">Description</label>
                                            <textarea name="description" class="kt-input kt-input-sm text-xs w-full" rows="2" placeholder="Description..."></textarea>
                                        </div>
                                        <div>
                                            <label class="text-[11px] font-medium text-muted-foreground">Audience Scope</label>
                                            <select name="audience" class="kt-select kt-select-sm text-xs w-full" required>
                                                <option value="internal">Internal (All Staff)</option>
                                                <option value="public">Public (General Community)</option>
                                                <option value="departmental">Departmental (Restricted)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-[11px] font-medium text-muted-foreground">Target Department (if departmental)</label>
                                            <select name="department_id" class="kt-select kt-select-sm text-xs w-full">
                                                <option value="">-- Select Department --</option>
                                                @foreach($departments as $dept)
                                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="flex items-center gap-2 pt-1">
                                            <input type="checkbox" name="is_pinned" value="1" class="kt-checkbox">
                                            <span class="text-xs text-muted-foreground">Pin to Top</span>
                                        </div>
                                        <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary w-full justify-center">Create Board</button>
                                    </form>
                                </div>
                            </details>
                        @endif
                    </div>
                </div>

                {{-- Board Search & Audience Filter --}}
                <div class="px-6 pt-4">
                    <form method="GET" action="{{ route('intranet.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <input type="text" name="board_search" value="{{ request('board_search') }}" placeholder="Search boards..." class="kt-input kt-input-sm text-xs">
                        <select name="board_audience" class="kt-select kt-select-sm text-xs">
                            <option value="">All Audiences</option>
                            <option value="internal" @selected(request('board_audience') === 'internal')>Internal</option>
                            <option value="public" @selected(request('board_audience') === 'public')>Public</option>
                            <option value="departmental" @selected(request('board_audience') === 'departmental')>Departmental</option>
                        </select>
                        <div class="flex items-center gap-2">
                            <button type="submit" class="kt-btn kt-btn-sm kt-btn-outline text-xs">Filter Boards</button>
                            @if(request('board_search') || request('board_audience'))
                                <a href="{{ route('intranet.index') }}" class="text-xs text-muted-foreground hover:underline">Reset</a>
                            @endif
                        </div>
                    </form>
                </div>

                <div class="kt-card-body p-6 space-y-6">
                    @forelse($messageBoards as $board)
                        <div class="p-4 rounded-lg border border-border bg-card/60 space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div>
                                    <h4 class="text-sm font-bold text-foreground flex items-center gap-2">
                                        <a href="{{ route('intranet.boards.show', $board) }}" class="hover:text-primary transition-colors">
                                            {{ $board->title }}
                                        </a>
                                        @if($board->is_pinned)
                                            <span class="kt-badge kt-badge-xs kt-badge-warning">Pinned</span>
                                        @endif
                                        <span class="kt-badge kt-badge-xs kt-badge-outline uppercase">{{ $board->audience }}</span>
                                        @if($board->department)
                                            <span class="kt-badge kt-badge-xs kt-badge-secondary">{{ $board->department->name }}</span>
                                        @endif
                                    </h4>
                                    <p class="text-xs text-muted-foreground mt-0.5">{{ $board->description }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('intranet.boards.show', $board) }}" class="kt-btn kt-btn-xs kt-btn-outline">
                                        View Board ({{ $board->posts_count }} posts) &rarr;
                                    </a>

                                    <details class="relative inline-block text-start">
                                        <summary class="kt-btn kt-btn-xs kt-btn-primary cursor-pointer list-none">+ Post</summary>
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
                                                @if($currentUser->can('intranet.boards.manage') || $currentUser->can('intranet.manage'))
                                                    <div class="flex items-center gap-2">
                                                        <input type="checkbox" name="is_pinned" value="1" class="kt-checkbox">
                                                        <span class="text-xs text-muted-foreground">Pin to Board Top</span>
                                                    </div>
                                                @endif
                                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary w-full justify-center">Post to Board</button>
                                            </form>
                                        </div>
                                    </details>
                                </div>
                            </div>

                            {{-- Recent Posts Snippet --}}
                            <div class="space-y-2 pt-1">
                                @forelse($board->posts as $post)
                                    <div class="p-2.5 rounded bg-muted/20 border border-border/70 text-xs space-y-1">
                                        <div class="flex items-center justify-between font-semibold">
                                            <span class="text-foreground flex items-center gap-1.5">
                                                {{ $post->title }}
                                                @if($post->is_announcement)
                                                    <span class="kt-badge kt-badge-xs kt-badge-primary">Notice</span>
                                                @endif
                                                @if($post->is_pinned)
                                                    <span class="kt-badge kt-badge-xs kt-badge-warning">Pinned</span>
                                                @endif
                                            </span>
                                            <span class="text-[10px] text-muted-foreground">{{ $post->created_at?->format('d/m/Y H:i') }}</span>
                                        </div>
                                        <p class="text-muted-foreground line-clamp-2">{{ $post->content }}</p>
                                        <div class="text-[11px] text-primary/80 pt-0.5 flex items-center justify-between">
                                            <span>Posted by {{ $post->author?->first_name }} {{ $post->author?->last_name }}</span>
                                            <a href="{{ route('intranet.boards.show', $board) }}" class="hover:underline">Read discussion &rarr;</a>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-xs text-muted-foreground py-1 italic">No messages on this board yet.</div>
                                @endforelse
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-sm text-muted-foreground">No message boards found matching the criteria.</div>
                    @endforelse

                    @if($messageBoards->hasPages())
                        <div class="pt-2">
                            {{ $messageBoards->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sidebar: Upcoming Events + Controlled Policies Quick Access --}}
        <div class="space-y-6">
            {{-- Upcoming Events --}}
            <div class="kt-card">
                <div class="kt-card-header flex-wrap gap-2">
                    <h3 class="kt-card-title text-sm font-bold flex items-center gap-2">
                        <i class="ki-filled ki-calendar text-primary text-base"></i>
                        Institutional Calendar
                    </h3>
                    @if($currentUser->can('intranet.events.manage') || $currentUser->can('intranet.manage'))
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
                                    <textarea name="description" class="kt-input kt-input-sm text-xs" rows="2" placeholder="Description / Agenda"></textarea>
                                    <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary w-full justify-center">Save Event</button>
                                </form>
                            </div>
                        </details>
                    @endif
                </div>

                {{-- Events Filter Form --}}
                <div class="p-4 border-b border-border bg-muted/10 space-y-2">
                    <form method="GET" action="{{ route('intranet.index') }}" class="space-y-2">
                        <input type="text" name="event_search" value="{{ request('event_search') }}" placeholder="Search events..." class="kt-input kt-input-xs text-xs w-full">
                        <div class="grid grid-cols-2 gap-2">
                            <select name="event_category" class="kt-select kt-select-xs text-xs">
                                <option value="">All Categories</option>
                                <option value="all_hands" @selected(request('event_category') === 'all_hands')>All-Hands</option>
                                <option value="training" @selected(request('event_category') === 'training')>Training/CME</option>
                                <option value="board_meeting" @selected(request('event_category') === 'board_meeting')>Board Meeting</option>
                                <option value="departmental" @selected(request('event_category') === 'departmental')>Departmental</option>
                                <option value="social" @selected(request('event_category') === 'social')>Social</option>
                            </select>
                            <select name="event_timeframe" class="kt-select kt-select-xs text-xs">
                                <option value="upcoming" @selected(request('event_timeframe', 'upcoming') === 'upcoming')>Upcoming</option>
                                <option value="past" @selected(request('event_timeframe') === 'past')>Past</option>
                                <option value="all" @selected(request('event_timeframe') === 'all')>All</option>
                            </select>
                        </div>
                        <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline w-full justify-center">Filter Events</button>
                    </form>
                </div>

                <div class="kt-card-body p-4 space-y-3">
                    @forelse($upcomingEvents as $event)
                        @php
                            $userRsvp = $event->userRsvp($currentUser);
                            $attendingCount = $event->rsvps->where('status', 'attending')->count();
                        @endphp
                        <div class="p-3 rounded-lg border border-border bg-card text-xs space-y-2">
                            <div class="flex items-start justify-between gap-1">
                                <div>
                                    <span class="font-bold text-foreground text-sm">{{ $event->title }}</span>
                                    <div class="text-[11px] text-muted-foreground mt-0.5">
                                        {{ $event->description }}
                                    </div>
                                </div>
                                <span class="kt-badge kt-badge-xs kt-badge-outline uppercase">{{ str_replace('_', ' ', $event->category) }}</span>
                            </div>

                            <div class="text-[11px] text-primary font-semibold flex items-center gap-1.5">
                                <i class="ki-filled ki-time text-xs"></i>
                                <span>{{ $event->event_date?->format('d/m/Y') }}</span>
                                @if($event->start_time)
                                    <span>&bull; {{ substr($event->start_time, 0, 5) }}</span>
                                @endif
                            </div>

                            <div class="text-[11px] text-muted-foreground flex items-center justify-between">
                                <span class="flex items-center gap-1">
                                    <i class="ki-filled ki-geolocation text-xs"></i>
                                    {{ $event->location }}
                                </span>
                                <span class="font-medium text-emerald-600 dark:text-emerald-400">
                                    {{ $attendingCount }} Attending
                                </span>
                            </div>

                            {{-- RSVP Actions --}}
                            <div class="pt-2 border-t border-border flex items-center justify-between gap-1">
                                <span class="text-[11px] text-muted-foreground">Your RSVP:</span>
                                <div class="flex items-center gap-1">
                                    <form action="{{ route('intranet.events.rsvp', $event) }}" method="POST" class="inline">
                                        @csrf
                                        <input type="hidden" name="status" value="attending">
                                        <button type="submit" class="kt-btn kt-btn-xs {{ $userRsvp?->status === 'attending' ? 'kt-btn-primary' : 'kt-btn-outline' }}">
                                            Yes
                                        </button>
                                    </form>
                                    <form action="{{ route('intranet.events.rsvp', $event) }}" method="POST" class="inline">
                                        @csrf
                                        <input type="hidden" name="status" value="tentative">
                                        <button type="submit" class="kt-btn kt-btn-xs {{ $userRsvp?->status === 'tentative' ? 'kt-btn-warning' : 'kt-btn-outline' }}">
                                            Maybe
                                        </button>
                                    </form>
                                    <form action="{{ route('intranet.events.rsvp', $event) }}" method="POST" class="inline">
                                        @csrf
                                        <input type="hidden" name="status" value="declined">
                                        <button type="submit" class="kt-btn kt-btn-xs {{ $userRsvp?->status === 'declined' ? 'kt-btn-danger' : 'kt-btn-outline' }}">
                                            No
                                        </button>
                                    </form>
                                </div>
                            </div>

                            {{-- Event Management Actions (Edit/Delete) --}}
                            @if($currentUser->can('intranet.events.manage') || $currentUser->can('intranet.manage'))
                                <div class="pt-1 flex items-center justify-end gap-2 text-[11px]">
                                    <details class="relative inline-block text-start">
                                        <summary class="text-primary hover:underline cursor-pointer list-none">Edit</summary>
                                        <div class="absolute right-0 mt-1 w-72 p-3 bg-card rounded-lg shadow-xl border border-border z-30 space-y-2">
                                            <div class="text-xs font-semibold text-foreground">Edit Event</div>
                                            <form action="{{ route('intranet.events.update', $event) }}" method="POST" class="space-y-2">
                                                @csrf
                                                @method('PUT')
                                                <input type="text" name="title" value="{{ $event->title }}" class="kt-input kt-input-xs text-xs" required>
                                                <input type="date" name="event_date" value="{{ $event->event_date?->toDateString() }}" class="kt-input kt-input-xs text-xs" required>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <input type="time" name="start_time" value="{{ substr((string) $event->start_time, 0, 5) }}" class="kt-input kt-input-xs text-xs">
                                                    <input type="time" name="end_time" value="{{ substr((string) $event->end_time, 0, 5) }}" class="kt-input kt-input-xs text-xs">
                                                </div>
                                                <input type="text" name="location" value="{{ $event->location }}" class="kt-input kt-input-xs text-xs" required>
                                                <select name="category" class="kt-select kt-select-xs text-xs" required>
                                                    @foreach(App\Modules\Intranet\Models\IntranetEvent::CATEGORIES as $cat)
                                                        <option value="{{ $cat }}" @selected($event->category === $cat)>{{ str_replace('_', ' ', $cat) }}</option>
                                                    @endforeach
                                                </select>
                                                <textarea name="description" class="kt-input kt-input-xs text-xs" rows="2">{{ $event->description }}</textarea>
                                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary w-full justify-center">Save Changes</button>
                                            </form>
                                        </div>
                                    </details>

                                    <form action="{{ route('intranet.events.destroy', $event) }}" method="POST" onsubmit="return confirm('Delete this event?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-destructive hover:underline">Delete</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-xs text-muted-foreground text-center py-4">No events found matching the criteria.</div>
                    @endforelse

                    @if($upcomingEvents->hasPages())
                        <div class="pt-2">
                            {{ $upcomingEvents->links() }}
                        </div>
                    @endif
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
                    @forelse($controlledPolicies as $policy)
                        <div class="p-2.5 rounded border border-border bg-card text-xs flex items-center justify-between">
                            <div>
                                <span class="font-semibold text-foreground">{{ $policy->title }}</span>
                                <div class="text-[10px] text-muted-foreground">{{ $policy->document_number }} &bull; Rev {{ $policy->revision_number }}</div>
                            </div>
                            <a href="{{ route('document-management.documents.show', $policy) }}" class="kt-btn kt-btn-xs kt-btn-outline">
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
