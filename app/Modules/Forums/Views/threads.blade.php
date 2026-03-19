@extends('layouts.metronic.app')

@section('title', 'Forums Threads')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Forums</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Thread Queue</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Live discussion threads with status and response depth.</p>
                </div>
                <a href="{{ route('forums.hub') }}" class="kt-btn kt-btn-outline">Back to Hub</a>
            </div>
        </div>

        <div class="kt-card kt-card-grid min-w-full">
            <div class="kt-card-header">
                <h3 class="kt-card-title">All Threads</h3>
                <form method="GET" action="{{ route('forums.threads.index') }}" class="flex items-center gap-2">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search threads…" class="kt-input min-w-[220px]" />
                    <select name="status" onchange="this.form.submit()" class="kt-select min-w-[140px]">
                        <option value="">All Statuses</option>
                        <option value="open" @selected($status === 'open')>Open</option>
                        <option value="closed" @selected($status === 'closed')>Closed</option>
                        <option value="flagged" @selected($status === 'flagged')>Flagged</option>
                    </select>
                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary">Search</button>
                    @if($search !== '' || $status !== '')
                        <a href="{{ route('forums.threads.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">Clear</a>
                    @endif
                </form>
            </div>
            <div class="kt-card-content">
                <div class="kt-scrollable-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th>Title</th>
                                <th>Channel</th>
                                <th>Status</th>
                                <th>Posts</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @forelse($threads as $thread)
                                <tr>
                                    <td>
                                        <p class="font-medium">{{ $thread->title }}</p>
                                        <p class="text-xs text-muted-foreground">{{ $thread->slug }}</p>
                                    </td>
                                    <td>{{ $thread->channel?->name ?: 'Unknown Channel' }}</td>
                                    <td>
                                        @php
                                            $threadStatusClass = match($thread->status) {
                                                'open' => 'kt-badge-success',
                                                'closed' => 'kt-badge-muted',
                                                'flagged' => 'kt-badge-danger',
                                                default => 'kt-badge-outline',
                                            };
                                        @endphp
                                        <span class="kt-badge {{ $threadStatusClass }} capitalize">{{ $thread->status }}</span>
                                    </td>
                                    <td>{{ $thread->posts_count }}</td>
                                    <td>{{ $thread->updated_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-8 text-center text-muted-foreground" colspan="5">No threads found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $threads->links() }}</div>
            </div>
        </div>
    </section>
@endsection
