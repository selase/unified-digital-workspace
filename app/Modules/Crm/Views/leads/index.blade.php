@extends('layouts.metronic.app')

@section('title', 'Leads & Inquiries Directory')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">Leads & Inquiries Registry</h1>
            <p class="text-xs text-muted-foreground">Comprehensive search, lifecycle management, and conversion of client prospects.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('crm.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">CRM Hub</a>
            <a href="{{ route('crm.pipeline') }}" class="kt-btn kt-btn-sm kt-btn-primary">Kanban Pipeline</a>
        </div>
    </div>

    {{-- Filter & Search Bar --}}
    <div class="kt-card p-4">
        <form method="GET" action="{{ route('crm.leads.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div>
                <input type="text" name="search" value="{{ $search }}" class="kt-input" placeholder="Search by name, email, phone, reference...">
            </div>
            <div>
                <select name="status" class="kt-select">
                    <option value="">All Statuses</option>
                    @foreach(['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'converted' => 'Converted', 'lost' => 'Lost'] as $k => $label)
                        <option value="{{ $k }}" @selected($status === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select name="source" class="kt-select">
                    <option value="">All Sources</option>
                    @foreach(['walk_in' => 'Walk-In', 'website_enquiry' => 'Website Form', 'property_listing' => 'Property Listing', 'referral' => 'Referral', 'direct' => 'Direct Outreach'] as $k => $label)
                        <option value="{{ $k }}" @selected($source === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="kt-btn kt-btn-primary w-full justify-center">Filter</button>
                @if($search || $status || $source)
                    <a href="{{ route('crm.leads.index') }}" class="kt-btn kt-btn-outline">Clear</a>
                @endif
            </div>
        </form>
    </div>

    {{-- Leads Table --}}
    <div class="kt-card">
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Prospect</th>
                        <th>Source</th>
                        <th>Organisation</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Captured</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leads as $lead)
                        <tr>
                            <td class="font-mono text-xs font-semibold">
                                <a href="{{ route('crm.leads.show', $lead) }}" class="text-primary hover:underline">
                                    {{ $lead->reference_code }}
                                </a>
                            </td>
                            <td>
                                <div class="font-semibold text-foreground">{{ $lead->full_name }}</div>
                                <div class="text-xs text-muted-foreground">{{ $lead->email ?? $lead->phone ?? 'No contact' }}</div>
                            </td>
                            <td>
                                <span class="kt-badge kt-badge-xs kt-badge-outline uppercase">{{ str_replace('_', ' ', $lead->source) }}</span>
                            </td>
                            <td class="text-xs text-muted-foreground">{{ $lead->organisation ?? '—' }}</td>
                            <td>
                                @if($lead->status === 'converted')
                                    <span class="kt-badge kt-badge-xs kt-badge-success">Converted</span>
                                @elseif($lead->status === 'lost')
                                    <span class="kt-badge kt-badge-xs kt-badge-destructive">Lost</span>
                                @elseif($lead->status === 'qualified')
                                    <span class="kt-badge kt-badge-xs kt-badge-primary">Qualified</span>
                                @else
                                    <span class="kt-badge kt-badge-xs kt-badge-warning uppercase">{{ $lead->status }}</span>
                                @endif
                            </td>
                            <td class="text-xs text-muted-foreground">
                                {{ $lead->assignedTo?->first_name ? $lead->assignedTo->first_name.' '.$lead->assignedTo->last_name : 'Unassigned' }}
                            </td>
                            <td class="text-xs text-muted-foreground">{{ $lead->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="text-end">
                                <div class="inline-flex items-center gap-1">
                                    <a href="{{ route('crm.leads.show', $lead) }}" class="kt-btn kt-btn-xs kt-btn-outline">View</a>
                                    @can('crm.leads.manage')
                                        <a href="{{ route('crm.leads.edit', $lead) }}" class="kt-btn kt-btn-xs kt-btn-outline">Edit</a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-8 text-sm text-muted-foreground">No leads found matching criteria.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($leads->hasPages())
            <div class="p-4 border-t border-border">
                {{ $leads->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

