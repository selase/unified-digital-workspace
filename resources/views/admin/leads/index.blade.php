@extends('layouts.metronic.app')

@section('title', 'Enterprise Leads')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Growth</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Enterprise Leads</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Review inbound enterprise requests and follow up.</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="overflow-x-auto">
                <table class="kt-table">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>Name</th>
                            <th>Email</th>
                            <th>Company</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-foreground">
                        @forelse ($leads as $lead)
                            <tr>
                                <td class="font-medium text-foreground">{{ $lead->name }}</td>
                                <td>{{ $lead->email }}</td>
                                <td>{{ $lead->company ?? 'N/A' }}</td>
                                <td>
                                    <span class="kt-badge kt-badge-outline kt-badge-primary">{{ strtoupper($lead->status) }}</span>
                                </td>
                                <td>{{ $lead->created_at->diffForHumans() }}</td>
                                <td class="text-right">
                                    <a href="{{ route('admin.leads.show', $lead->id) }}" class="kt-btn kt-btn-sm kt-btn-outline">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-muted-foreground">No leads found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5">
                {{ $leads->links() }}
            </div>
        </div>
    </section>
@endsection
