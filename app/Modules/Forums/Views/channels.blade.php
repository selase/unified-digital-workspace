@extends('layouts.metronic.app')

@section('title', 'Forums Channels')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Forums</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Channel Directory</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Structured channels across operations, support, and collaboration streams.</p>
                </div>
                <a href="{{ route('forums.hub') }}" class="kt-btn kt-btn-outline">Back to Hub</a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="overflow-x-auto">
                <table class="kt-table table-auto kt-table-border">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Threads</th>
                            <th>Flagged</th>
                            <th>Locked</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-foreground">
                        @forelse($channels as $channel)
                            <tr>
                                <td>
                                    <p class="font-medium">{{ $channel->name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ $channel->description ?: 'No description' }}</p>
                                </td>
                                <td>{{ $channel->slug }}</td>
                                <td>{{ $channel->threads_count }}</td>
                                <td>{{ $channel->flagged_threads_count }}</td>
                                <td>
                                    <span class="kt-badge {{ $channel->is_locked ? 'kt-badge-warning' : 'kt-badge-success' }}">
                                        {{ $channel->is_locked ? 'Locked' : 'Open' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-8 text-center text-muted-foreground" colspan="5">No channels found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $channels->links() }}</div>
        </div>
    </section>
@endsection
