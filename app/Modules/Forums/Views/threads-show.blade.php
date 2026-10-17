@extends('layouts.metronic.app')

@section('title', $thread->title . ' - Discussion')

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="{{ $thread->title }}"
        description="Posted in #{{ $thread->channel?->name ?? 'general' }} by {{ $thread->author ? $thread->author->first_name . ' ' . $thread->author->last_name : 'Staff Member' }} &bull; {{ $thread->created_at?->format('d/m/Y H:i') }}"
    >
        <x-slot:actions>
            <a href="{{ route('forums.threads.index') }}" class="kt-btn kt-btn-outline">
                All Threads
            </a>
            @if($thread->channel)
                <a href="{{ route('forums.channels.show', $thread->channel) }}" class="kt-btn kt-btn-outline">
                    #{{ $thread->channel->name }}
                </a>
            @endif

            @can('forums.moderate')
                {{-- Pin Toggle --}}
                <form action="{{ route('forums.threads.pin', $thread) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="kt-btn kt-btn-outline text-xs">
                        {{ $thread->pinned_at ? 'Unpin Notice' : 'Pin to Top' }}
                    </button>
                </form>

                {{-- Lock Toggle --}}
                <form action="{{ route('forums.threads.lock', $thread) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="kt-btn kt-btn-outline text-xs">
                        {{ $thread->status === 'locked' ? 'Unlock Thread' : 'Lock Thread' }}
                    </button>
                </form>

                {{-- Flag Toggle --}}
                <form action="{{ route('forums.threads.flag', $thread) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="kt-btn kt-btn-outline text-xs {{ $thread->status === 'flagged' ? 'text-emerald-600' : 'text-amber-600' }}">
                        {{ $thread->status === 'flagged' ? 'Resolve Flag' : 'Flag for Review' }}
                    </button>
                </form>
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

    {{-- Status Banner if Locked or Flagged --}}
    @if($thread->status === 'locked')
        <div class="rounded-xl border border-border bg-muted/40 p-4 flex items-center gap-3">
            <svg class="h-5 w-5 text-muted-foreground shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            <div class="text-xs">
                <span class="font-semibold text-foreground">Thread is Locked.</span>
                <span class="text-muted-foreground">New contributions are restricted by institutional moderation.</span>
            </div>
        </div>
    @elseif($thread->status === 'flagged')
        <div class="rounded-xl border border-amber-500/20 bg-amber-500/10 p-4 flex items-center gap-3 text-amber-800 dark:text-amber-300">
            <svg class="h-5 w-5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="text-xs">
                <span class="font-semibold">Thread Flagged for Review.</span>
                <span>This topic is currently pending moderator assessment against community standards.</span>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Posts Stream (8 cols) --}}
        <div class="lg:col-span-8 flex flex-col gap-6">
            <div class="flex flex-col gap-4">
                @forelse($posts as $index => $post)
                    <div class="rounded-xl border border-border bg-card p-5 {{ $index === 0 ? 'ring-1 ring-primary/20 shadow-sm' : '' }}">
                        <div class="flex items-center justify-between pb-3 border-b border-border gap-3">
                            <div class="flex items-center gap-3">
                                <div class="h-9 w-9 rounded-full bg-primary/10 text-primary font-bold flex items-center justify-center text-xs shrink-0 border border-primary/20">
                                    {{ strtoupper(substr($post->author?->first_name ?: 'U', 0, 1) . substr($post->author?->last_name ?: 'S', 0, 1)) }}
                                </div>
                                <div>
                                    <span class="font-semibold text-foreground text-sm">
                                        {{ $post->author ? $post->author->first_name . ' ' . $post->author->last_name : 'Staff Member' }}
                                    </span>
                                    <div class="text-xs text-muted-foreground flex items-center gap-2">
                                        <span>{{ $post->created_at?->diffForHumans() }}</span>
                                        @if($index === 0)
                                            <span class="inline-flex items-center px-1.5 py-0.2 rounded text-2xs font-semibold bg-primary/10 text-primary">
                                                Author / Opening Post
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <span class="text-xs text-muted-foreground">#{{ $index + 1 }}</span>
                        </div>

                        <div class="mt-4 text-sm text-foreground leading-relaxed whitespace-pre-wrap">
                            {{ $post->body }}
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-border p-8 text-center text-muted-foreground text-sm">
                        No messages logged for this thread.
                    </div>
                @endforelse
            </div>

            <div class="pt-2">
                {{ $posts->links() }}
            </div>

            {{-- Reply Form --}}
            @if($thread->status !== 'locked' && !$thread->channel?->is_locked)
                @can('forums.post')
                    <x-metronic.card title="Contribute to Discussion" subtitle="Post a reply as {{ auth()->user()?->name }}">
                        <form method="POST" action="{{ route('forums.threads.reply', $thread) }}" class="flex flex-col gap-4">
                            @csrf
                            <div class="space-y-1.5">
                                <textarea
                                    name="body"
                                    rows="4"
                                    required
                                    placeholder="Add clinical context, operational updates, or question responses…"
                                    class="kt-input w-full p-3 h-auto"
                                >{{ old('body') }}</textarea>
                                @error('body')
                                    <p class="text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex items-center justify-end">
                                <button type="submit" class="kt-btn kt-btn-primary">
                                    Post Reply
                                </button>
                            </div>
                        </form>
                    </x-metronic.card>
                @endcan
            @endif
        </div>

        {{-- Thread Sidebar (4 cols) --}}
        <div class="lg:col-span-4 flex flex-col gap-6">
            <x-metronic.card title="Topic Metadata" subtitle="Thread status and parameters">
                <div class="space-y-3.5 text-xs">
                    <div class="flex items-center justify-between pb-3 border-b border-border">
                        <span class="text-muted-foreground">Channel</span>
                        @if($thread->channel)
                            <a href="{{ route('forums.channels.show', $thread->channel) }}" class="font-semibold text-primary hover:underline">
                                #{{ $thread->channel->name }}
                            </a>
                        @else
                            <span class="font-semibold text-muted-foreground">#General</span>
                        @endif
                    </div>

                    <div class="flex items-center justify-between pb-3 border-b border-border">
                        <span class="text-muted-foreground">Status</span>
                        @if($thread->status === 'open')
                            <x-metronic.badge variant="success" size="sm">Open</x-metronic.badge>
                        @elseif($thread->status === 'locked')
                            <x-metronic.badge variant="secondary" size="sm">Locked</x-metronic.badge>
                        @elseif($thread->status === 'flagged')
                            <x-metronic.badge variant="warning" size="sm">Flagged</x-metronic.badge>
                        @endif
                    </div>

                    <div class="flex items-center justify-between pb-3 border-b border-border">
                        <span class="text-muted-foreground">Total Replies</span>
                        <span class="font-bold text-foreground">{{ $posts->total() }}</span>
                    </div>

                    <div class="flex items-center justify-between pb-3 border-b border-border">
                        <span class="text-muted-foreground">Started On</span>
                        <span class="font-medium text-foreground">{{ $thread->created_at?->format('d/m/Y') }}</span>
                    </div>

                    @if(!empty($thread->tags))
                        <div>
                            <span class="text-muted-foreground block mb-1.5">Classifications & Tags</span>
                            <div class="flex flex-wrap gap-1">
                                @foreach($thread->tags as $t)
                                    <span class="text-2xs px-2 py-0.5 rounded bg-muted text-foreground border border-border">
                                        #{{ $t }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </x-metronic.card>

            <x-metronic.card title="Discussion Guidelines">
                <div class="space-y-2 text-xs text-muted-foreground">
                    <p>All comments are attributable to your authenticated staff identity.</p>
                    <p>Maintain professional discourse aligned with organizational standards.</p>
                </div>
            </x-metronic.card>
        </div>
    </div>
</div>
@endsection

