@extends('layouts.metronic.app')

@section('title', 'Commercial Deals Directory')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">Commercial Deals Directory</h1>
            <p class="text-xs text-muted-foreground">Search, monitor and track all active, won, and lost deal opportunities.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('crm.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">CRM Hub</a>
            <a href="{{ route('crm.pipeline') }}" class="kt-btn kt-btn-sm kt-btn-primary">Kanban Pipeline</a>
        </div>
    </div>

    {{-- Filter & Search Bar --}}
    <div class="kt-card p-4">
        <form method="GET" action="{{ route('crm.deals.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <input type="text" name="search" value="{{ $search }}" class="kt-input" placeholder="Search by deal title or client account...">
            </div>
            <div>
                <select name="stage" class="kt-select">
                    <option value="">All Pipeline Stages</option>
                    @foreach($stages as $k => $label)
                        <option value="{{ $k }}" @selected($stage === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="kt-btn kt-btn-primary w-full justify-center">Filter</button>
                @if($search || $stage)
                    <a href="{{ route('crm.deals.index') }}" class="kt-btn kt-btn-outline">Clear</a>
                @endif
            </div>
        </form>
    </div>

    {{-- Deals Table --}}
    <div class="kt-card">
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr>
                        <th>Deal Title</th>
                        <th>Client Account</th>
                        <th>Value</th>
                        <th>Stage</th>
                        <th>Win %</th>
                        <th>Expected Close</th>
                        <th>Owner</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deals as $deal)
                        <tr>
                            <td class="font-semibold text-foreground">
                                <a href="{{ route('crm.deals.show', $deal) }}" class="text-primary hover:underline">
                                    {{ $deal->title }}
                                </a>
                            </td>
                            <td>
                                @if($deal->account)
                                    <a href="{{ route('crm.accounts.show', $deal->account) }}" class="text-xs text-muted-foreground hover:underline">
                                        {{ $deal->account->name }}
                                    </a>
                                @else
                                    <span class="text-xs text-muted-foreground">—</span>
                                @endif
                            </td>
                            <td class="font-mono text-xs font-semibold text-primary">{{ $deal->formattedValue() }}</td>
                            <td>
                                <span class="kt-badge kt-badge-xs kt-badge-outline uppercase">{{ str_replace('_', ' ', $deal->stage) }}</span>
                            </td>
                            <td><span class="kt-badge kt-badge-xs kt-badge-outline">{{ $deal->probability }}%</span></td>
                            <td class="text-xs text-muted-foreground">{{ $deal->expected_close_date?->format('d/m/Y') ?? '—' }}</td>
                            <td class="text-xs text-muted-foreground">{{ $deal->owner?->first_name ? $deal->owner->first_name.' '.$deal->owner->last_name : 'Unassigned' }}</td>
                            <td class="text-end">
                                <div class="inline-flex items-center gap-1">
                                    <a href="{{ route('crm.deals.show', $deal) }}" class="kt-btn kt-btn-xs kt-btn-outline">View</a>
                                    @can('crm.deals.manage')
                                        <a href="{{ route('crm.deals.edit', $deal) }}" class="kt-btn kt-btn-xs kt-btn-outline">Edit</a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-8 text-sm text-muted-foreground">No deals found matching criteria.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($deals->hasPages())
            <div class="p-4 border-t border-border">
                {{ $deals->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

