@extends('layouts.metronic.app')

@section('title', 'Global Subscriptions')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Billing</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Global Subscriptions</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Track recurring plans and renewal schedules.</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="flex flex-wrap items-end justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-foreground">Subscriptions</h2>
                    <p class="text-xs text-muted-foreground">Search and filter by status.</p>
                </div>
                <form action="{{ route('admin.billing.subscriptions.index') }}" method="GET" class="flex flex-wrap items-center gap-3">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-muted-foreground" for="subscription-search">Search</label>
                        <input id="subscription-search" type="text" name="search" class="kt-input w-56" placeholder="Search subscriptions" value="{{ request('search') }}" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-muted-foreground" for="subscription-status">Status</label>
                        <select id="subscription-status" name="status" class="kt-select w-40" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>Expired</option>
                        </select>
                    </div>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="kt-table table-auto kt-table-border">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>Tenant</th>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>Frequency</th>
                            <th>Status</th>
                            <th>Next Billing</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-foreground">
                        @forelse ($subscriptions as $subscription)
                            <tr>
                                <td>
                                    <div class="flex flex-col">
                                        @if($subscription->tenant)
                                            <a href="{{ route('tenants.show', $subscription->tenant->uuid) }}" class="font-medium text-foreground hover:text-primary">
                                                {{ $subscription->tenant->name }}
                                            </a>
                                            <span class="text-xs text-muted-foreground">{{ $subscription->tenant->email }}</span>
                                        @else
                                            <span class="text-sm text-muted-foreground">Unknown Tenant</span>
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $subscription->provider_plan ?? $subscription->name ?? 'N/A' }}</td>
                                <td>
                                    <span class="text-xs text-muted-foreground">N/A</span>
                                </td>
                                <td>
                                    <span class="text-xs text-muted-foreground">N/A</span>
                                </td>
                                <td>
                                    @if($subscription->provider_status === 'active')
                                        <span class="kt-badge kt-badge-success">Active</span>
                                    @else
                                        <span class="kt-badge kt-badge-secondary">{{ ucfirst($subscription->provider_status) }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-sm text-muted-foreground">
                                        {{ $subscription->ends_at ? $subscription->ends_at->format('M d, Y') : 'Auto-renew' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-sm text-muted-foreground">No subscriptions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex justify-end">
                {{ $subscriptions->links() }}
            </div>
        </div>
    </section>
@endsection
