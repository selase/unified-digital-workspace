@extends('layouts.metronic.app')

@section('title', 'Client Accounts Directory')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">Client Accounts Directory</h1>
            <p class="text-xs text-muted-foreground">Corporate, individual, and institutional client organisations with active service relationships.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('crm.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">CRM Hub</a>
            <a href="{{ route('crm.pipeline') }}" class="kt-btn kt-btn-sm kt-btn-primary">Kanban Pipeline</a>
        </div>
    </div>

    {{-- Filter & Search Bar --}}
    <div class="kt-card p-4">
        <form method="GET" action="{{ route('crm.accounts.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div>
                <input type="text" name="search" value="{{ $search }}" class="kt-input" placeholder="Search by name, code, email, phone...">
            </div>
            <div>
                <select name="account_type" class="kt-select">
                    <option value="">All Account Types</option>
                    @foreach(['corporate' => 'Corporate', 'individual' => 'Individual', 'government' => 'Government', 'partner' => 'Partner'] as $k => $label)
                        <option value="{{ $k }}" @selected($accountType === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select name="status" class="kt-select">
                    <option value="">All Statuses</option>
                    @foreach(['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'] as $k => $label)
                        <option value="{{ $k }}" @selected($status === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="kt-btn kt-btn-primary w-full justify-center">Filter</button>
                @if($search || $accountType || $status)
                    <a href="{{ route('crm.accounts.index') }}" class="kt-btn kt-btn-outline">Clear</a>
                @endif
            </div>
        </form>
    </div>

    {{-- Accounts Table --}}
    <div class="kt-card">
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Account Name</th>
                        <th>Type</th>
                        <th>Industry</th>
                        <th>Contact Email / Phone</th>
                        <th>Contacts</th>
                        <th>Deals</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $account)
                        <tr>
                            <td class="font-mono text-xs font-semibold">
                                <a href="{{ route('crm.accounts.show', $account) }}" class="text-primary hover:underline">
                                    {{ $account->code }}
                                </a>
                            </td>
                            <td>
                                <div class="font-semibold text-foreground">{{ $account->name }}</div>
                                @if($account->portal_user_id)
                                    <span class="kt-badge kt-badge-2xs kt-badge-success">Portal Enabled</span>
                                @endif
                            </td>
                            <td><span class="kt-badge kt-badge-xs kt-badge-outline uppercase">{{ $account->account_type }}</span></td>
                            <td class="text-xs text-muted-foreground">{{ $account->industry ?? '—' }}</td>
                            <td class="text-xs text-muted-foreground">{{ $account->email ?? $account->phone ?? '—' }}</td>
                            <td class="text-xs font-semibold">{{ $account->contacts_count }}</td>
                            <td class="text-xs font-semibold">{{ $account->deals_count }}</td>
                            <td>
                                @if($account->status === 'active')
                                    <span class="kt-badge kt-badge-xs kt-badge-success">Active</span>
                                @else
                                    <span class="kt-badge kt-badge-xs kt-badge-warning uppercase">{{ $account->status }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="inline-flex items-center gap-1">
                                    <a href="{{ route('crm.accounts.show', $account) }}" class="kt-btn kt-btn-xs kt-btn-outline">View</a>
                                    @can('crm.accounts.manage')
                                        <a href="{{ route('crm.accounts.edit', $account) }}" class="kt-btn kt-btn-xs kt-btn-outline">Edit</a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-8 text-sm text-muted-foreground">No client accounts found matching criteria.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($accounts->hasPages())
            <div class="p-4 border-t border-border">
                {{ $accounts->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

