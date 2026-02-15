@extends('layouts.metronic.app')

@section('title', 'Global Transactions')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Billing</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Global Transactions</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Review payment activity across all tenants.</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="flex flex-wrap items-end justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-foreground">Transactions</h2>
                    <p class="text-xs text-muted-foreground">Search and filter by status.</p>
                </div>
                <form action="{{ route('admin.billing.transactions.index') }}" method="GET" class="flex flex-wrap items-center gap-3">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-muted-foreground" for="transaction-search">Search</label>
                        <input id="transaction-search" type="text" name="search" class="kt-input w-56" placeholder="Search transactions" value="{{ request('search') }}" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-muted-foreground" for="transaction-status">Status</label>
                        <select id="transaction-status" name="status" class="kt-select w-40" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>Success</option>
                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                        </select>
                    </div>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="kt-table" id="kt_table_transactions">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>Transaction ID</th>
                            <th>Tenant</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-foreground">
                        @forelse ($transactions as $transaction)
                            <tr>
                                <td>
                                    <span class="font-medium text-foreground">{{ $transaction->provider_transaction_id }}</span>
                                </td>
                                <td>
                                    <div class="flex flex-col">
                                        @if($transaction->tenant)
                                            <a href="{{ route('tenants.show', $transaction->tenant->uuid) }}" class="font-medium text-foreground hover:text-primary">
                                                {{ $transaction->tenant->name }}
                                            </a>
                                            <span class="text-xs text-muted-foreground">{{ $transaction->tenant->email }}</span>
                                        @else
                                            <span class="text-sm text-muted-foreground">Unknown Tenant</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    {{ \App\Libraries\Helper::formatAmountWithCurrencySymbol($transaction->amount, $transaction->currency, true) }}
                                </td>
                                <td>
                                    @if($transaction->status === 'success' || $transaction->status === 'succeeded')
                                        <span class="kt-badge kt-badge-success">Success</span>
                                    @elseif($transaction->status === 'pending')
                                        <span class="kt-badge kt-badge-warning">Pending</span>
                                    @else
                                        <span class="kt-badge kt-badge-destructive">{{ ucfirst($transaction->status) }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-sm text-muted-foreground">{{ $transaction->created_at->format('M d, Y H:i') }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-sm text-muted-foreground">No transactions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex justify-end">
                {{ $transactions->links() }}
            </div>
        </div>
    </section>
@endsection
