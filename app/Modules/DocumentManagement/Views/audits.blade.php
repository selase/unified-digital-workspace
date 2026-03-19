@extends('layouts.metronic.app')

@section('title', 'Document Audits')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Document Management</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Audit Timeline</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Read-only timeline of document activity events across your tenant.</p>
                </div>
                <a class="kt-btn kt-btn-outline" href="{{ route('document-management.index') }}">Back to Hub</a>
            </div>
        </div>

        <div class="kt-card kt-card-grid min-w-full">
            <div class="kt-card-header">
                <h3 class="kt-card-title">Audit Events</h3>
                <form method="GET" action="{{ route('document-management.audits.index') }}" class="flex items-center gap-2">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search events…" class="kt-input min-w-[220px]" />
                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary">Search</button>
                    @if($search !== '')
                        <a href="{{ route('document-management.audits.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">Clear</a>
                    @endif
                </form>
            </div>
            <div class="kt-card-content">
                <div class="kt-scrollable-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th>Event</th>
                                <th>Document</th>
                                <th>User</th>
                                <th>Metadata</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @forelse($audits as $audit)
                                <tr>
                                    <td><span class="kt-badge kt-badge-outline">{{ $audit->event }}</span></td>
                                    <td>{{ $audit->document?->title ?: 'Unknown document' }}</td>
                                    <td>{{ $audit->user_id ?: 'System' }}</td>
                                    <td class="text-xs text-muted-foreground">{{ json_encode($audit->metadata ?? [], JSON_UNESCAPED_SLASHES) }}</td>
                                    <td>{{ $audit->created_at?->toDayDateTimeString() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-8 text-center text-muted-foreground" colspan="5">No audit entries found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $audits->links() }}</div>
            </div>
        </div>
    </section>
@endsection
