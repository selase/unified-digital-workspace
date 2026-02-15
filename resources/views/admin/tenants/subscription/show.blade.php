@extends('layouts.metronic.app')

@section('title', __('locale.labels.invoices'))

@section('content')
    @php
        $tenant = $subscription->tenant ?? null;
        $tenantLogo = $tenant?->logo_url ?: $tenant?->gravatar;
        $status = $subscription->provider_status ?? $subscription->status ?? 'unknown';
        $statusClass = match ($status) {
            'active', 'paid', 'succeeded' => 'kt-badge-success',
            'past_due', 'pending' => 'kt-badge-warning',
            'cancelled', 'canceled' => 'kt-badge-secondary',
            'failed' => 'kt-badge-destructive',
            default => 'kt-badge-secondary',
        };
        $subscriptionLogs = $subscription_logs ?? collect();
        $subscriptionTransaction = $subscription_transaction ?? null;
        $topupRoute = Route::has('tenants.subscriptions.admin-add-credit')
            ? route('tenants.subscriptions.admin-add-credit', $subscription->uuid ?? $subscription->id)
            : null;
    @endphp

    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Tenant Subscription</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Subscription Overview</h1>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="kt-badge {{ $statusClass }}">{{ strtoupper($status) }}</span>
                        @if($tenant)
                            <span class="text-xs text-muted-foreground">{{ $tenant->name }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    @can('update tenant')
                        @if($topupRoute)
                            <button type="button" class="kt-btn kt-btn-primary" data-kt-modal-toggle="#admin_topup_tenant_credit">
                                <i class="ki-filled ki-plus text-base"></i>
                                {{ __('Add Credit') }}
                            </button>
                        @else
                            <span class="text-xs text-muted-foreground">Top up unavailable</span>
                        @endif
                    @endcan
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 rounded-xl border border-border bg-background p-6">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <h2 class="text-lg font-semibold text-foreground">Invoices & Payments</h2>
                    <span class="text-xs text-muted-foreground">Recent activity for this subscription.</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="kt-table" id="tenant-subscription-invoices">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th>Transaction ID</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th class="text-right">Invoice</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @forelse ($subscriptionLogs as $log)
                                @php
                                    $logStatus = $log->status ?? $log->provider_status ?? 'unknown';
                                    $logStatusClass = match ($logStatus) {
                                        'active', 'paid', 'succeeded' => 'kt-badge-success',
                                        'past_due', 'pending' => 'kt-badge-warning',
                                        'cancelled', 'canceled' => 'kt-badge-secondary',
                                        'failed' => 'kt-badge-destructive',
                                        default => 'kt-badge-secondary',
                                    };
                                    $logDate = $log->date ?? $log->created_at;
                                @endphp
                                <tr>
                                    <td>
                                        @if(Route::has('tenants.subscriptions.invoice'))
                                            <a href="{{ route('tenants.subscriptions.invoice', $log->uuid ?? $log->id) }}" class="text-foreground hover:text-primary">
                                                {{ $log->transaction_id ?? $log->provider_transaction_id ?? $log->id }}
                                            </a>
                                        @else
                                            <span>{{ $log->transaction_id ?? $log->provider_transaction_id ?? $log->id }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(isset($log->amount))
                                            {{ App\Libraries\Helper::formatAmountWithCurrencySymbol($log->amount, $log->currency ?? null, isset($log->amount) && is_int($log->amount)) }}
                                        @else
                                            <span class="text-xs text-muted-foreground">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="kt-badge {{ $logStatusClass }}">{{ strtoupper($logStatus) }}</span>
                                    </td>
                                    <td>
                                        <span class="text-xs text-muted-foreground">{{ $logDate?->format('M d, Y') }}</span>
                                    </td>
                                    <td class="text-right">
                                        <button type="button" class="kt-btn kt-btn-sm kt-btn-outline">Download</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-sm text-muted-foreground">No invoices recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-border bg-background p-6">
                <div class="flex items-center gap-3">
                    @if($tenantLogo)
                        <img src="{{ $tenantLogo }}" alt="{{ $tenant?->name ?? 'Tenant' }}" class="size-12 rounded-full object-cover" />
                    @else
                        <div class="size-12 rounded-full bg-muted flex items-center justify-center text-xs text-muted-foreground">NA</div>
                    @endif
                    <div>
                        <div class="text-base font-semibold text-foreground">{{ $tenant?->name ?? 'Unknown Tenant' }}</div>
                        <div class="text-xs text-muted-foreground">{{ $tenant?->email }}</div>
                    </div>
                </div>

                <div class="mt-6 space-y-4 text-sm">
                    <div>
                        <div class="text-xs uppercase text-muted-foreground">Plan</div>
                        <div class="text-sm text-foreground">{{ $subscription->provider_plan ?? $subscription->name ?? 'N/A' }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-muted-foreground">Subscription ID</div>
                        <div class="text-sm text-foreground">{{ $subscription->provider_id ?? $subscription->transaction_id ?? $subscription->id }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-muted-foreground">Current Period End</div>
                        <div class="text-sm text-foreground">{{ $subscription->current_period_end?->format('M d, Y') ?? $subscription->ends_at?->format('M d, Y') ?? 'Auto-renew' }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-muted-foreground">Payment Method</div>
                        <div class="text-sm text-foreground">
                            {{ ucfirst(str_replace('_', ' ', $subscriptionTransaction?->payment_method ?? 'N/A')) }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-muted-foreground">Status</div>
                        <span class="kt-badge {{ $statusClass }}">{{ strtoupper($status) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @include('admin.tenants.subscription.modals.create')
@endsection

@push('styles')
    <link href="{{ asset('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/plugins/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
@endpush

@push('vendor-scripts')
    <script src="{{ asset('assets/plugins/global/plugins.bundle.js') }}"></script>
    <script src="{{ asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>
@endpush

@push('custom-scripts')
    <script src="{{ asset('js/scripts.js') }}"></script>
    <script>
        $('#tenant-subscription-invoices').DataTable({
            "language": {
                "lengthMenu": "Show _MENU_",
            },
            "lengthMenu": [[25, 50, 100, 250, 500, 1000, -1], [25, 50, 100, 250, 500, 1000, "All"]],
            "responsive": true,
            "dom":
                "<'flex flex-wrap items-center justify-between gap-4 mb-4'lf>" +
                "<'table-responsive'tr>" +
                "<'flex flex-wrap items-center justify-between gap-4 mt-4'ip>",
        });
    </script>
@endpush
