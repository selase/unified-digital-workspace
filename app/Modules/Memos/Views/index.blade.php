@extends('layouts.metronic.app')

@section('title', 'Memos Hub')

@section('content')
    @php
        $draftCount = (int) ($statusCounts[\App\Modules\Memos\Models\Memo::STATUS_DRAFT] ?? 0);
        $pendingVerificationCount = (int) ($statusCounts[\App\Modules\Memos\Models\Memo::STATUS_PENDING] ?? 0);
        $sentCount = (int) ($statusCounts[\App\Modules\Memos\Models\Memo::STATUS_SENT] ?? 0);
        $acknowledgedCount = (int) ($statusCounts[\App\Modules\Memos\Models\Memo::STATUS_ACKNOWLEDGED] ?? 0);
    @endphp

    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Memo Management</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Memos Hub</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Track sensitive memos, acknowledgements, minutes, and actions.</p>
                </div>
                <a href="{{ route('api.memos.v1.memos.index') }}" class="kt-btn kt-btn-outline">Open API</a>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-4">
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Draft</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $draftCount }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Pending Verification</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $pendingVerificationCount }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Sent</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $sentCount }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Acknowledged</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $acknowledgedCount }}</p>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-12">
            <div class="rounded-xl border border-border bg-background p-6 xl:col-span-8">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-foreground">Recent Memos</h2>
                    <span class="text-xs text-muted-foreground">Latest 10 updates</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th>Subject</th>
                                <th>Sender</th>
                                <th>Status</th>
                                <th>Recipients</th>
                                <th>Last Update</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @forelse($recentMemos as $memo)
                                <tr>
                                    <td class="font-medium">{{ $memo->subject }}</td>
                                    <td>{{ $memo->sender?->displayName() ?: $memo->sender?->email ?: 'System' }}</td>
                                    <td><span class="kt-badge kt-badge-outline">{{ str_replace('_', ' ', ucfirst($memo->status)) }}</span></td>
                                    <td>{{ $memo->recipients->count() }}</td>
                                    <td>{{ $memo->updated_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-muted-foreground">No memos created yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-border bg-background p-6 xl:col-span-4">
                <h2 class="text-lg font-semibold text-foreground">Execution Snapshot</h2>
                <div class="mt-4 space-y-3">
                    <div class="rounded-lg border border-border p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Acknowledgement Required</p>
                        <p class="mt-2 text-xl font-semibold text-foreground">{{ $acknowledgementRequiredCount }}</p>
                    </div>
                    <div class="rounded-lg border border-border p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Pending Acknowledgement</p>
                        <p class="mt-2 text-xl font-semibold {{ $pendingAcknowledgementCount > 0 ? 'text-destructive' : 'text-foreground' }}">
                            {{ $pendingAcknowledgementCount }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-border p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Open Actions</p>
                        <p class="mt-2 text-xl font-semibold text-foreground">{{ $openActionCount }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
