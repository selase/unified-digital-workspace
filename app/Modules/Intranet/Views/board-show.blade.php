@extends('layouts.metronic.app')

@section('title', $board->title . ' - Institutional Intranet')

@section('content')
<div class="space-y-6">
    {{-- Breadcrumb / Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-6 rounded-xl bg-gradient-to-r from-primary/10 via-primary/5 to-transparent border border-primary/20">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('intranet.index') }}" class="text-xs text-primary hover:underline flex items-center gap-1">
                    &larr; Back to Intranet Hub
                </a>
                <span class="text-xs text-muted-foreground">&bull;</span>
                <span class="kt-badge kt-badge-xs kt-badge-outline uppercase">{{ $board->audience }}</span>
                @if($board->department)
                    <span class="kt-badge kt-badge-xs kt-badge-secondary">{{ $board->department->name }}</span>
                @endif
                @if($board->is_pinned)
                    <span class="kt-badge kt-badge-xs kt-badge-warning">Pinned Board</span>
                @endif
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight text-foreground">{{ $board->title }}</h1>
            <p class="text-sm text-muted-foreground mt-0.5">{{ $board->description }}</p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <details class="relative inline-block text-start">
                <summary class="kt-btn kt-btn-primary kt-btn-sm cursor-pointer list-none">+ New Post</summary>
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
                                <span class="text-xs text-muted-foreground">Pin to Top</span>
                            </div>
                        @endif
                        <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary w-full justify-center">Post to Board</button>
                    </form>
                </div>
            </details>

            @if($currentUser->can('intranet.boards.manage') || $currentUser->can('intranet.manage'))
                <details class="relative inline-block text-start">
                    <summary class="kt-btn kt-btn-outline kt-btn-sm cursor-pointer list-none">Edit Board</summary>
                    <div class="absolute right-0 mt-1 w-80 p-4 bg-card rounded-lg shadow-xl border border-border z-30 space-y-3">
                        <div class="text-xs font-semibold text-foreground">Edit Message Board</div>
                        <form action="{{ route('intranet.boards.update', $board) }}" method="POST" class="space-y-2">
                            @csrf
                            @method('PUT')
                            <div>
                                <label class="text-[11px] font-medium text-muted-foreground">Title</label>
                                <input type="text" name="title" value="{{ $board->title }}" class="kt-input kt-input-sm text-xs w-full" required>
                            </div>
                            <div>
                                <label class="text-[11px] font-medium text-muted-foreground">Description</label>
                                <textarea name="description" class="kt-input kt-input-sm text-xs w-full" rows="2">{{ $board->description }}</textarea>
                            </div>
                            <div>
                                <label class="text-[11px] font-medium text-muted-foreground">Audience Scope</label>
                                <select name="audience" class="kt-select kt-select-sm text-xs w-full" required>
                                    <option value="internal" @selected($board->audience === 'internal')>Internal (All Staff)</option>
                                    <option value="public" @selected($board->audience === 'public')>Public (General Community)</option>
                                    <option value="departmental" @selected($board->audience === 'departmental')>Departmental (Restricted)</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[11px] font-medium text-muted-foreground">Department (if departmental)</label>
                                <select name="department_id" class="kt-select kt-select-sm text-xs w-full">
                                    <option value="">-- None --</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" @selected($board->department_id === $dept->id)>{{ $dept->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex items-center gap-2 pt-1">
                                <input type="checkbox" name="is_pinned" value="1" @checked($board->is_pinned) class="kt-checkbox">
                                <span class="text-xs text-muted-foreground">Pin to Top</span>
                            </div>
                            <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary w-full justify-center">Save Changes</button>
                        </form>
                    </div>
                </details>

                <form action="{{ route('intranet.boards.destroy', $board) }}" method="POST" onsubmit="return confirm('Delete this entire board and all its messages?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="kt-btn kt-btn-danger kt-btn-sm">Delete Board</button>
                </form>
            @endif
        </div>
    </div>

    @if(session('message'))
        <div class="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-sm flex items-center gap-2">
            <i class="ki-filled ki-check-circle text-base"></i>
            {{ session('message') }}
        </div>
    @endif

    {{-- Filter Bar --}}
    <div class="p-4 rounded-xl border border-border bg-card">
        <form method="GET" action="{{ route('intranet.boards.show', $board) }}" class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search messages in this board..." class="kt-input kt-input-sm text-xs w-full sm:w-72">
                <button type="submit" class="kt-btn kt-btn-sm kt-btn-outline text-xs">Search</button>
                @if(request('search') || request('announcements_only'))
                    <a href="{{ route('intranet.boards.show', $board) }}" class="text-xs text-muted-foreground hover:underline">Clear</a>
                @endif
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                <label class="flex items-center gap-1.5 text-xs text-muted-foreground cursor-pointer">
                    <input type="checkbox" name="announcements_only" value="1" @checked(request('announcements_only')) onchange="this.form.submit()" class="kt-checkbox">
                    <span>Show Official Notices Only</span>
                </label>
            </div>
        </form>
    </div>

    {{-- Posts List --}}
    <div class="space-y-4">
        @forelse($posts as $post)
            @php
                $canManagePost = $currentUser->can('intranet.boards.manage') || $currentUser->can('intranet.manage') || $post->author_id === (string) $currentUser->uuid;
            @endphp
            <div class="p-5 rounded-xl border border-border bg-card space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-border pb-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold text-foreground">{{ $post->title }}</h3>
                            @if($post->is_pinned)
                                <span class="kt-badge kt-badge-xs kt-badge-warning">Pinned</span>
                            @endif
                            @if($post->is_announcement)
                                <span class="kt-badge kt-badge-xs kt-badge-primary">Official Notice</span>
                            @endif
                        </div>
                        <div class="text-xs text-muted-foreground mt-0.5">
                            Posted by <span class="font-semibold text-foreground">{{ $post->author?->first_name }} {{ $post->author?->last_name }}</span> &bull; {{ $post->created_at?->format('d/m/Y H:i') }}
                        </div>
                    </div>

                    {{-- Actions: Pin, Edit, Delete --}}
                    <div class="flex items-center gap-2 text-xs">
                        @if($currentUser->can('intranet.boards.manage') || $currentUser->can('intranet.manage'))
                            <form action="{{ route('intranet.posts.pin', $post) }}" method="POST">
                                @csrf
                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline">
                                    {{ $post->is_pinned ? 'Unpin' : 'Pin' }}
                                </button>
                            </form>
                        @endif

                        @if($canManagePost)
                            <details class="relative inline-block text-start">
                                <summary class="kt-btn kt-btn-xs kt-btn-outline cursor-pointer list-none">Edit</summary>
                                <div class="absolute right-0 mt-1 w-80 p-4 bg-card rounded-lg shadow-xl border border-border z-30 space-y-2">
                                    <div class="text-xs font-semibold text-foreground">Edit Message</div>
                                    <form action="{{ route('intranet.posts.update', $post) }}" method="POST" class="space-y-2">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="title" value="{{ $post->title }}" class="kt-input kt-input-sm text-xs" required>
                                        <textarea name="content" class="kt-input kt-input-sm text-xs" rows="3" required>{{ $post->content }}</textarea>
                                        <div class="flex items-center gap-2">
                                            <input type="checkbox" name="is_announcement" value="1" @checked($post->is_announcement) class="kt-checkbox">
                                            <span class="text-xs text-muted-foreground">Official Notice</span>
                                        </div>
                                        @if($currentUser->can('intranet.boards.manage') || $currentUser->can('intranet.manage'))
                                            <div class="flex items-center gap-2">
                                                <input type="checkbox" name="is_pinned" value="1" @checked($post->is_pinned) class="kt-checkbox">
                                                <span class="text-xs text-muted-foreground">Pinned</span>
                                            </div>
                                        @endif
                                        <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary w-full justify-center">Save Changes</button>
                                    </form>
                                </div>
                            </details>

                            <form action="{{ route('intranet.posts.destroy', $post) }}" method="POST" onsubmit="return confirm('Delete this message?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-danger">Delete</button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="text-sm text-foreground leading-relaxed whitespace-pre-line">
                    {{ $post->content }}
                </div>

                {{-- Replies Section --}}
                <div class="pt-3 border-t border-border space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-muted-foreground">
                            Replies ({{ $post->replies->count() }})
                        </span>

                        <details class="relative inline-block text-start">
                            <summary class="kt-btn kt-btn-xs kt-btn-outline cursor-pointer list-none">+ Reply</summary>
                            <div class="absolute right-0 mt-1 w-80 p-3 bg-card rounded-lg shadow-xl border border-border z-30 space-y-2">
                                <div class="text-xs font-semibold text-foreground">Reply to {{ $post->title }}</div>
                                <form action="{{ route('intranet.posts.replies.store', $post) }}" method="POST" class="space-y-2">
                                    @csrf
                                    <textarea name="content" class="kt-input kt-input-sm text-xs" rows="3" placeholder="Write your reply..." required></textarea>
                                    <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary w-full justify-center">Submit Reply</button>
                                </form>
                            </div>
                        </details>
                    </div>

                    @if($post->replies->isNotEmpty())
                        <div class="space-y-2 pl-4 border-l-2 border-primary/30">
                            @foreach($post->replies as $reply)
                                @php
                                    $canManageReply = $currentUser->can('intranet.boards.manage') || $currentUser->can('intranet.manage') || $reply->author_id === (string) $currentUser->uuid;
                                @endphp
                                <div class="p-3 rounded-lg bg-muted/20 border border-border text-xs space-y-1">
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold text-foreground">
                                            {{ $reply->author?->first_name }} {{ $reply->author?->last_name }}
                                        </span>
                                        <div class="flex items-center gap-2">
                                            <span class="text-[10px] text-muted-foreground">{{ $reply->created_at?->format('d/m/Y H:i') }}</span>
                                            @if($canManageReply)
                                                <form action="{{ route('intranet.posts.destroy', $reply) }}" method="POST" onsubmit="return confirm('Delete this reply?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-destructive hover:underline text-[10px]">Delete</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                    <p class="text-muted-foreground leading-relaxed whitespace-pre-line">{{ $reply->content }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="p-12 text-center rounded-xl border border-dashed border-border bg-card">
                <i class="ki-filled ki-messages text-3xl text-muted-foreground mb-2"></i>
                <h4 class="text-sm font-bold text-foreground">No messages found on this board</h4>
                <p class="text-xs text-muted-foreground mt-1">Be the first to post a message or collaboration notice!</p>
            </div>
        @endforelse

        @if($posts->hasPages())
            <div class="pt-4">
                {{ $posts->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

