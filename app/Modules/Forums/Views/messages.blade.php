@extends('layouts.metronic.app')

@section('title', 'Forums Messages')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Forums</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Message Center</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Direct and scoped internal communications with read tracking.</p>
                </div>
                <div class="kt-badge kt-badge-primary">{{ $unreadCount }} unread</div>
            </div>
        </div>

        <div class="kt-card kt-card-grid min-w-full">
            <div class="kt-card-header">
                <h3 class="kt-card-title">All Messages</h3>
                <form method="GET" action="{{ route('forums.messages.index') }}" class="flex items-center gap-2">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search messages…" class="kt-input min-w-[220px]" />
                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary">Search</button>
                    @if($search !== '')
                        <a href="{{ route('forums.messages.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">Clear</a>
                    @endif
                </form>
            </div>
            <div class="kt-card-content">
                <div class="kt-scrollable-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th>Subject</th>
                                <th>Sender</th>
                                <th>Recipients</th>
                                <th>Scope</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @forelse($messages as $message)
                                <tr>
                                    <td>
                                        <p class="font-medium">{{ $message->subject }}</p>
                                        <p class="text-xs text-muted-foreground">{{ \Illuminate\Support\Str::limit($message->body, 90) }}</p>
                                    </td>
                                    <td>{{ $message->sender?->displayName() ?: 'Unknown Sender' }}</td>
                                    <td>{{ $message->recipients_count }}</td>
                                    <td>{{ data_get($message->visibility, 'scope', 'direct') }}</td>
                                    <td>{{ $message->updated_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-8 text-center text-muted-foreground" colspan="5">No messages found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $messages->links() }}</div>
            </div>
        </div>
    </section>
@endsection
