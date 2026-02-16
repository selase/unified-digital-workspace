@extends('layouts.metronic.app')

@section('title', __('Finance & Sales'))

@section('content')
    @php
        $refundCount = $transactions->where('type', 'refund')->count();
        $failedCount = $transactions->where('status', 'failed')->count();
    @endphp

    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div>
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Finance</p>
                <h1 class="mt-2 text-2xl font-semibold text-foreground">Finance & Sales</h1>
                <p class="mt-2 text-sm text-muted-foreground">Track revenue performance and transaction activity.</p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-5">
            <div class="rounded-xl border border-border bg-background p-6">
                <div class="text-xs uppercase text-muted-foreground">Gross Sales Volume</div>
                <div class="mt-3 text-2xl font-semibold text-foreground">
                    ${{ number_format($stats['total_volume'] / 100, 2) }}
                </div>
                <p class="mt-2 text-xs text-muted-foreground">Total processed volume.</p>
            </div>
            <div class="rounded-xl border border-border bg-background p-6">
                <div class="text-xs uppercase text-muted-foreground">Successful Payments</div>
                <div class="mt-3 text-2xl font-semibold text-foreground">{{ $stats['transaction_count'] }}</div>
                <p class="mt-2 text-xs text-muted-foreground">Transactions completed successfully.</p>
            </div>
            <div class="rounded-xl border border-border bg-background p-6">
                <div class="text-xs uppercase text-muted-foreground">Refund Count</div>
                <div class="mt-3 text-2xl font-semibold text-foreground">{{ $refundCount }}</div>
                <p class="mt-2 text-xs text-muted-foreground">Refund rows in current result set.</p>
            </div>
            <div class="rounded-xl border border-border bg-background p-6">
                <div class="text-xs uppercase text-muted-foreground">Failed Payments</div>
                <div class="mt-3 text-2xl font-semibold text-foreground">{{ $failedCount }}</div>
                <p class="mt-2 text-xs text-muted-foreground">Marked as failed in current result set.</p>
            </div>
            <div class="rounded-xl border border-border bg-background p-6">
                <div class="text-xs uppercase text-muted-foreground">Revenue Performance</div>
                <div class="mt-4" id="kt_finance_chart" style="height: 200px"></div>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="flex flex-wrap items-end justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-foreground">Transactions</h2>
                    <p class="text-xs text-muted-foreground">Search and filter recent payments.</p>
                </div>
                <form action="" method="GET" class="flex flex-wrap items-end gap-3">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-muted-foreground" for="transaction-search">Search</label>
                        <input id="transaction-search" type="text" name="search" value="{{ request('search') }}" class="kt-input w-64" placeholder="Search Transactions" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-muted-foreground" for="transaction-status">Status</label>
                        <select id="transaction-status" class="kt-select" name="status" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="succeeded" {{ request('status') === 'succeeded' ? 'selected' : '' }}>Succeeded</option>
                            <option value="refunded" {{ request('status') === 'refunded' ? 'selected' : '' }}>Refunded</option>
                            <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                        </select>
                    </div>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="kt-table table-auto kt-table-border">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>Transaction ID</th>
                            <th>Customer</th>
                            <th class="text-right">Amount</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Date</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-foreground">
                        @forelse($transactions as $transaction)
                            @php
                                $badgeClass = match ($transaction->status) {
                                    'succeeded', 'paid' => 'kt-badge-success',
                                    'refunded' => 'kt-badge-primary',
                                    'pending' => 'kt-badge-warning',
                                    default => 'kt-badge-destructive',
                                };
                                $customerEmail = data_get($transaction->meta, 'customer_email')
                                    ?? data_get($transaction->meta, 'email')
                                    ?? 'No Email';
                                $customerName = data_get($transaction->meta, 'customer_name')
                                    ?? data_get($transaction->meta, 'name')
                                    ?? 'Unknown';
                            @endphp
                            <tr>
                                <td>
                                    <div class="flex flex-col">
                                        <span class="font-medium text-foreground">{{ $transaction->provider_transaction_id }}</span>
                                        <span class="text-xs text-muted-foreground">{{ ucfirst($transaction->provider) }} · {{ ucfirst($transaction->type) }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex flex-col">
                                        <span class="font-medium text-foreground">{{ $customerEmail }}</span>
                                        <span class="text-xs text-muted-foreground">{{ $customerName }}</span>
                                    </div>
                                </td>
                                <td class="text-right">
                                    <div class="font-semibold text-foreground">
                                        @if($transaction->type === 'refund') - @endif
                                        ${{ number_format($transaction->amount / 100, 2) }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">{{ strtoupper($transaction->currency) }}</div>
                                </td>
                                <td class="text-center">
                                    <span class="kt-badge {{ $badgeClass }}">{{ strtoupper($transaction->status) }}</span>
                                </td>
                                <td class="text-center">
                                    <div class="text-sm text-foreground">{{ $transaction->created_at->format('M d, Y') }}</div>
                                    <div class="text-xs text-muted-foreground">{{ $transaction->created_at->format('H:i') }}</div>
                                </td>
                                <td class="text-right">
                                    <div class="kt-menu kt-menu-default" data-kt-menu="true">
                                        <div class="kt-menu-item" data-kt-menu-item-offset="0, 10px" data-kt-menu-item-placement="bottom-end" data-kt-menu-item-toggle="dropdown" data-kt-menu-item-trigger="click">
                                            <button type="button" class="kt-menu-toggle kt-btn kt-btn-sm kt-btn-outline">
                                                Actions
                                                <span class="kt-menu-arrow">
                                                    <i class="ki-filled ki-down text-xs"></i>
                                                </span>
                                            </button>
                                            <div class="kt-menu-dropdown w-40 py-2">
                                                <div class="kt-menu-item">
                                                    <button type="button" class="kt-menu-link w-full text-left" onclick='showTransactionDetails(@js($transaction->meta))'>Details</button>
                                                </div>
                                                @if($transaction->status === 'succeeded' && $transaction->type === 'payment')
                                                    <div class="kt-menu-item">
                                                        <form action="{{ route('tenant.finance.refund', $transaction->id) }}" method="POST" class="refund-transaction-form">
                                                            @csrf
                                                            <button type="submit" class="kt-menu-link w-full text-left text-destructive">Issue Refund</button>
                                                        </form>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-sm text-muted-foreground">No transactions found for this period.</td>
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

@push('vendor-scripts')
    <script src="{{ asset('assets/metronic/vendors/apexcharts/apexcharts.min.js') }}"></script>
@endpush

@push('custom-scripts')
    <script src="{{ asset('js/scripts.js') }}"></script>
    <script>
        const runConfirmDialog = (config, onConfirm) => {
            if (typeof Swal !== 'undefined') {
                Swal.fire(config).then(function (result) {
                    if (result.isConfirmed) {
                        onConfirm();
                    }
                });

                return;
            }

            if (window.confirm(config.text ?? 'Please confirm this action.')) {
                onConfirm();
            }
        };

        const showTransactionDetails = (meta) => {
            const content = JSON.stringify(meta ?? {}, null, 2);

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Transaction Metadata',
                    html: `<pre class="text-start text-xs overflow-auto max-h-[50vh] p-4 bg-muted rounded-lg">${content}</pre>`,
                    width: 700,
                    confirmButtonText: 'Close',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'kt-btn kt-btn-primary',
                    },
                });

                return;
            }

            alert(`Metadata: ${content}`);
        };

        document.querySelectorAll('.refund-transaction-form').forEach((form) => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                runConfirmDialog({
                    text: 'Are you sure you want to refund this transaction?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, refund',
                    cancelButtonText: 'Cancel',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'kt-btn kt-btn-danger',
                        cancelButton: 'kt-btn kt-btn-outline',
                    },
                }, () => form.submit());
            });
        });

        const chartOptions = {
            series: [{
                name: 'Gross Revenue',
                data: @json(collect($monthlyStats)->pluck('amount'))
            }],
            chart: {
                fontFamily: 'inherit',
                type: 'bar',
                height: 200,
                toolbar: { show: false }
            },
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    columnWidth: '50%'
                }
            },
            colors: ['#009EF7'],
            xaxis: {
                categories: @json(collect($monthlyStats)->pluck('label')),
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                labels: {
                    formatter: function (val) {
                        return "$" + val.toFixed(0);
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return "$" + val.toFixed(2);
                    }
                }
            }
        };

        const chart = new ApexCharts(document.querySelector("#kt_finance_chart"), chartOptions);
        chart.render();
    </script>
@endpush
