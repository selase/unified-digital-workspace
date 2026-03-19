@extends('layouts.metronic.app')

@section('title', 'Memo List')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Memo Management</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Memo List</h1>
                    <p class="mt-2 text-sm text-muted-foreground">All memos across statuses with sender and recipient tracking.</p>
                </div>
                <a href="{{ route('memos.index') }}" class="kt-btn kt-btn-outline">Back to Hub</a>
            </div>
        </div>

        <div class="kt-card kt-card-grid min-w-full">
            <div class="kt-card-header">
                <h3 class="kt-card-title">All Memos</h3>
                <form method="GET" action="{{ route('memos.list') }}" class="flex items-center gap-2">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search memos…" class="kt-input min-w-[220px]" />
                    <select name="status" onchange="this.form.submit()" class="kt-select min-w-[140px]">
                        <option value="">All Statuses</option>
                        <option value="draft" @selected($status === 'draft')>Draft</option>
                        <option value="pending_verification" @selected($status === 'pending_verification')>Pending Verification</option>
                        <option value="sent" @selected($status === 'sent')>Sent</option>
                        <option value="acknowledged" @selected($status === 'acknowledged')>Acknowledged</option>
                        <option value="closed" @selected($status === 'closed')>Closed</option>
                    </select>
                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary">Search</button>
                    @if($search !== '' || $status !== '')
                        <a href="{{ route('memos.list') }}" class="kt-btn kt-btn-sm kt-btn-outline">Clear</a>
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
                                <th>Status</th>
                                <th>Recipients</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @forelse($memos as $memo)
                                <tr>
                                    <td>
                                        <p class="font-medium">{{ $memo->subject }}</p>
                                        <p class="text-xs text-muted-foreground">ID: {{ $memo->uuid }}</p>
                                    </td>
                                    <td>{{ $memo->sender?->displayName() ?: $memo->sender?->email ?: 'System' }}</td>
                                    <td>
                                        @php
                                            $memoStatusClass = match($memo->status) {
                                                'draft' => 'kt-badge-muted',
                                                'pending_verification' => 'kt-badge-warning',
                                                'sent' => 'kt-badge-primary',
                                                'acknowledged' => 'kt-badge-success',
                                                'closed' => 'kt-badge-outline',
                                                default => 'kt-badge-outline',
                                            };
                                        @endphp
                                        <span class="kt-badge {{ $memoStatusClass }}">{{ str_replace('_', ' ', ucfirst($memo->status)) }}</span>
                                    </td>
                                    <td>{{ $memo->recipients_count }}</td>
                                    <td>{{ $memo->updated_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-8 text-center text-muted-foreground" colspan="5">No memos found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $memos->links() }}</div>
            </div>
        </div>
    </section>
@endsection
