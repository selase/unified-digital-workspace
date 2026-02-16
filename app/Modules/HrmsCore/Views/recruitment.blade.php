@extends('layouts.metronic.app')

@section('title', 'HRMS Recruitment')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">HRMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Recruitment Pipeline</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Track active job postings and linked requisitions.</p>
                </div>
                <a class="kt-btn kt-btn-outline" href="{{ route('hrms-core.index') }}">Back to Hub</a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="overflow-x-auto">
                <table class="kt-table table-auto kt-table-border">
                    <thead>
                    <tr class="text-xs uppercase text-muted-foreground">
                        <th>Posting</th>
                        <th>Requisition</th>
                        <th>Department</th>
                        <th>Applications</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody class="text-sm text-foreground">
                    @forelse($jobPostings as $jobPosting)
                        <tr>
                            <td>
                                <p class="font-medium">{{ $jobPosting->title }}</p>
                                <p class="text-xs text-muted-foreground">{{ $jobPosting->closing_date?->toDateString() ?: 'No closing date' }}</p>
                            </td>
                            <td>{{ $jobPosting->requisition?->title ?: 'No requisition' }}</td>
                            <td>{{ $jobPosting->requisition?->department?->name ?: '—' }}</td>
                            <td>{{ $jobPosting->applications_count }}</td>
                            <td>
                                <span class="kt-badge {{ $jobPosting->is_active ? 'kt-badge-success' : 'kt-badge-outline' }}">
                                    {{ $jobPosting->is_active ? 'Open' : 'Closed' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="py-8 text-center text-muted-foreground" colspan="5">No job postings found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $jobPostings->links() }}</div>
        </div>
    </section>
@endsection
