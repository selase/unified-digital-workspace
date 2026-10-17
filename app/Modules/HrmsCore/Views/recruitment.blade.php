@extends('layouts.metronic.app')

@section('title', 'HRMS Recruitment')

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="Recruitment Pipeline"
        description="Track institutional talent requisitions, published vacancies, and candidate application volumes."
    >
        <x-slot:actions>
            <a href="{{ route('hrms-core.index') }}" class="kt-btn kt-btn-outline">
                Back to Hub
            </a>
            <a href="{{ route('hrms-core.employees.index') }}" class="kt-btn kt-btn-outline">
                Staff Directory
            </a>
        </x-slot:actions>
    </x-metronic.page-header>

    <x-metronic.card title="Job Postings ({{ $jobPostings->total() }})" subtitle="Active and archived career opportunities">
        <div class="mb-5 pb-4 border-b border-border">
            <form method="GET" action="{{ route('hrms-core.recruitment.index') }}" class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[240px]">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search posting title…"
                        class="kt-input w-full"
                    />
                </div>
                <div class="w-44">
                    <select name="status" onchange="this.form.submit()" class="kt-select w-full">
                        <option value="">All Statuses</option>
                        <option value="open" @selected($status === 'open')>Open Only</option>
                        <option value="closed" @selected($status === 'closed')>Closed Only</option>
                    </select>
                </div>
                <button type="submit" class="kt-btn kt-btn-outline">Filter</button>
                @if($search || $status)
                    <a href="{{ route('hrms-core.recruitment.index') }}" class="kt-btn kt-btn-ghost text-xs text-muted-foreground">Reset</a>
                @endif
            </form>
        </div>

        <div class="kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr class="text-xs uppercase text-muted-foreground border-b border-border bg-muted/20">
                        <th class="py-3 px-4 font-semibold text-left">Posting Title</th>
                        <th class="py-3 px-4 font-semibold text-left">Requisition Reference</th>
                        <th class="py-3 px-4 font-semibold text-left">Target Department</th>
                        <th class="py-3 px-4 font-semibold text-center">Applications</th>
                        <th class="py-3 px-4 font-semibold text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border text-sm">
                    @forelse($jobPostings as $jobPosting)
                        <tr class="hover:bg-muted/10 transition-colors">
                            <td class="py-3.5 px-4 font-medium text-foreground">
                                <div class="font-semibold text-foreground">{{ $jobPosting->title }}</div>
                                <div class="text-xs text-muted-foreground flex items-center gap-1.5 mt-0.5">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    Closing: {{ $jobPosting->closing_date?->format('d/m/Y') ?: 'Open until filled' }}
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                @if($jobPosting->requisition)
                                    <span class="text-xs font-mono font-medium text-foreground bg-muted px-2 py-0.5 rounded border border-border">
                                        {{ $jobPosting->requisition->requisition_number ?: 'REQ-' . $jobPosting->requisition->id }}
                                    </span>
                                    <div class="text-xs text-muted-foreground mt-0.5">{{ Str::limit($jobPosting->requisition->title, 28) }}</div>
                                @else
                                    <span class="text-xs text-muted-foreground italic">—</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="text-sm text-foreground">
                                    {{ $jobPosting->requisition?->department?->name ?: 'Institutional' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-primary/10 text-primary border border-primary/20">
                                    {{ $jobPosting->applications_count ?? 0 }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                <x-metronic.badge :variant="$jobPosting->is_active ? 'success' : 'secondary'" size="sm">
                                    {{ $jobPosting->is_active ? 'Open' : 'Closed' }}
                                </x-metronic.badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-muted-foreground">
                                <p class="text-sm">No job postings found matching your filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 pt-4 border-t border-border">
            {{ $jobPostings->links() }}
        </div>
    </x-metronic.card>
</div>
@endsection
