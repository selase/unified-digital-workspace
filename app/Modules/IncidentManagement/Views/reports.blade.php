@extends('layouts.metronic.app')

@section('title', 'Incident Reports')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Incident Management</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Progress Reports</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Operational updates and internal notes attached to incident timelines.</p>
                </div>
                <a class="kt-btn kt-btn-outline" href="{{ route('incident-management.index') }}">Back to Hub</a>
            </div>
            <div class="mt-5 grid gap-3 md:grid-cols-2">
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Internal Reports</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $internalReports }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">External Reports</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $externalReports }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="overflow-x-auto">
                <table class="kt-table">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>Incident</th>
                            <th>Author</th>
                            <th>Type</th>
                            <th>Body</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-foreground">
                        @forelse($reports as $report)
                            <tr>
                                <td>{{ $report->incident?->reference_code }} - {{ $report->incident?->title }}</td>
                                <td>{{ $report->user?->displayName() ?: $report->user_id }}</td>
                                <td>
                                    <span class="kt-badge {{ $report->is_internal ? 'kt-badge-warning' : 'kt-badge-outline' }}">
                                        {{ $report->is_internal ? 'Internal' : 'External' }}
                                    </span>
                                </td>
                                <td class="max-w-[420px] truncate">{{ $report->body }}</td>
                                <td>{{ $report->created_at?->toDayDateTimeString() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-8 text-center text-muted-foreground" colspan="5">No progress reports found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $reports->links() }}</div>
        </div>
    </section>
@endsection
