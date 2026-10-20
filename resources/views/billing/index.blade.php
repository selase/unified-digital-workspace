@extends('layouts.metronic.app')

@section('title', 'Billing & Subscription')

@section('content')
    @php
        $savedPlan = isset($displayAgreement) ? $displayAgreement->snapshot['hybrid']['website_plans'][$displayAgreement->plan_slug] : null;
    @endphp
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Billing</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Billing & Subscription</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Track invoices, plan status, and monthly usage charges.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('tenant.pricing') }}" class="kt-btn kt-btn-primary">Upgrade Plan</a>
                    <a href="{{ route('tenant.settings.billing') }}" class="kt-btn kt-btn-outline">Billing Settings</a>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-4">
            <div class="kt-card p-6 xl:col-span-1">
                <p class="text-xs uppercase tracking-wide text-muted-foreground">{{ isset($displayAgreement) && $displayAgreement->effective_from->isFuture() ? 'Scheduled website plan' : 'Current website plan' }}</p>
                @if($savedPlan)
                    <h2 class="mt-3 text-3xl font-semibold text-foreground">{{ $displayAgreement->currency }} {{ number_format($savedPlan[$displayAgreement->currency], 2) }}</h2>
                    <p class="mt-2 text-sm text-muted-foreground">{{ $savedPlan['name'] }} · monthly website subscription</p>
                    <p class="mt-2 text-sm text-muted-foreground">{{ $displayAgreement->editor_seats }} website editor seats. Starts {{ $displayAgreement->effective_from->format('j M Y') }}. Additional purchased seats, modules, active access, eligible usage and taxes are shown separately on invoices.</p>
                @else
                    <h2 class="mt-3 text-3xl font-semibold text-foreground">{{ $tenant->billingCurrency() }} {{ ($tenant->billingCurrency() === 'GHS' && $tenant->package?->price_ghs === null) ? 'Not configured' : number_format((float) ($tenant->billingCurrency() === 'GHS' ? $tenant->package?->price_ghs : $tenant->package?->price), 2) }}</h2>
                    <p class="mt-2 text-sm text-muted-foreground">{{ $tenant->package?->name ?? 'No package selected' }}</p>
                @endif
                <div class="mt-6 space-y-2 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-muted-foreground">Next Invoice</span>
                        <span class="font-medium text-foreground">{{ isset($displayAgreement) ? ($displayAgreement->effective_from->isFuture() ? $displayAgreement->effective_from->copy()->addMonthNoOverflow() : today()->addMonthNoOverflow()->startOfMonth())->format('d/m/Y') : ($subscription?->current_period_end?->format('d/m/Y') ?? 'N/A') }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-muted-foreground">Subscription</span>
                        <span class="kt-badge kt-badge-success">{{ ucfirst($subscription?->provider_status ?? $tenant->status->value ?? (string) $tenant->status) }}</span>
                    </div>
                </div>
            </div>

            <div class="kt-card p-6 xl:col-span-1">
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Projected</p>
                <h2 class="mt-3 text-3xl font-semibold text-foreground">{{ $billingCurrency ?? $tenant->billingCurrency() }} {{ $accruedMetered === null ? 'Rates not configured' : number_format($accruedMetered, 2) }}</h2>
                <p class="mt-2 text-sm text-muted-foreground">{{ app(\App\Services\Tenancy\BillingAgreementService::class)->activeFor($tenant) ? 'Estimated full-month subscription, access and usage charges before applicable taxes.' : 'Accrued metered charges this month.' }}</p>
                <div class="mt-6">
                    <div class="h-2 rounded-full bg-accent">
                        <div class="h-2 rounded-full bg-primary" style="width: 65%"></div>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">Month to date estimate</p>
                </div>
            </div>

            <div class="kt-card p-6 xl:col-span-2">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">Spending Analytics</h2>
                        <p class="text-xs text-muted-foreground">Last six months revenue contribution.</p>
                    </div>
                </div>
                <div class="mt-4 h-[300px]" id="kt_billing_chart"></div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="kt-card p-6 xl:col-span-2">
                <div class="mb-4">
                    <h2 class="text-lg font-semibold text-foreground">Invoice History</h2>
                    <p class="text-xs text-muted-foreground">All officially issued invoices.</p>
                    <a href="{{ route('billing.invoices.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">View all invoices</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th>Invoice Number</th>
                                <th>Period</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @forelse($invoices as $invoice)
                                <tr>
                                    <td class="font-medium">{{ $invoice->number }}</td>
                                    <td>{{ $invoice->period_start->format('M Y') }}</td>
                                    <td>{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</td>
                                    <td>
                                        <span class="kt-badge {{ $invoice->status === 'paid' ? 'kt-badge-success' : 'kt-badge-warning' }}">
                                            {{ strtoupper($invoice->status) }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('billing.invoices.show', $invoice->id) }}" class="kt-btn kt-btn-sm kt-btn-outline">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-sm text-muted-foreground">No invoices issued yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="kt-card p-6">
                <div class="mb-4">
                    <h2 class="text-lg font-semibold text-foreground">Payment History</h2>
                </div>

                <div class="space-y-4">
                    @forelse($transactions as $transaction)
                        <div class="rounded-lg border border-border p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-medium text-foreground">{{ $transaction->provider_transaction_id ?: 'Payment' }}</p>
                                    <p class="text-xs text-muted-foreground">{{ $transaction->created_at->format('d/m/Y') }}</p>
                                </div>
                                <div class="text-end">
                                    <p class="font-semibold text-foreground">{{ $transaction->currency }} {{ number_format($transaction->amount / 100, 2) }}</p>
                                    <span class="kt-badge {{ in_array($transaction->status, ['success', 'succeeded'], true) ? 'kt-badge-success' : 'kt-badge-warning' }}">
                                        {{ strtoupper($transaction->status) }}
                                    </span>
                                </div>
                            </div>

                            @if(auth()->user()?->can('admin.tenants.update') && in_array($transaction->status, ['success', 'succeeded'], true))
                                <form action="{{ route('billing.refund', ['transaction' => $transaction->id, 'subdomain' => $tenant->slug]) }}" method="POST" class="mt-3" onsubmit="return confirm('Issue refund?')">
                                    @csrf
                                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-outline">Refund</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                            No payments found.
                        </div>
                    @endforelse
                </div>

                <div class="mt-4">
                    {{ $transactions->links() }}
                </div>
            </div>
        </div>
        <div class="kt-card p-6">
            <h2 class="text-lg font-semibold text-foreground">Billing email history</h2>
            <p class="mt-2 text-sm text-muted-foreground">Cycle notices arrive 7 days and 1 day before the new month. Reviewed invoices include secure checkout; GHS invoices use Paystack. Receipts follow verified payment. Sent means accepted by the mail transport, not proof of inbox delivery.</p>
            @forelse(($billingMessages ?? collect()) as $billingMessage)
                <div class="mt-4 border-t border-border pt-3 text-sm text-foreground">
                    <strong>{{ ucfirst(str_replace('_', ' ', $billingMessage->kind)) }}</strong> · {{ $billingMessage->status }}
                    <span class="block text-muted-foreground">{{ $billingMessage->recipient }} · {{ $billingMessage->created_at->format('j M Y H:i') }}</span>
                </div>
            @empty
                <p class="mt-4 text-sm text-muted-foreground">No billing emails recorded yet. Check your billing contact in Billing Settings.</p>
            @endforelse
        </div>
    </section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (! window.ApexCharts) {
                return;
            }

            const chartElement = document.querySelector('#kt_billing_chart');

            if (! chartElement) {
                return;
            }

            const options = {
                series: [{
                    name: 'Spending',
                    data: @json(collect($monthlyStats)->pluck('amount')->map(fn ($value) => $value / 100)),
                }],
                chart: {
                    fontFamily: 'Inter, sans-serif',
                    type: 'bar',
                    height: 300,
                    toolbar: { show: false },
                },
                plotOptions: {
                    bar: {
                        borderRadius: 6,
                        columnWidth: '50%',
                    },
                },
                colors: ['#1b84ff'],
                xaxis: {
                    categories: @json(collect($monthlyStats)->pluck('label')),
                    labels: {
                        style: { colors: '#6b7280' },
                    },
                },
                yaxis: {
                    labels: {
                        formatter: function (value) {
                            return @json($tenant->billingCurrency().' ') + value.toFixed(0);
                        },
                    },
                },
                grid: {
                    borderColor: '#e5e7eb',
                },
            };

            const chart = new ApexCharts(chartElement, options);
            chart.render();
        });
    </script>
@endpush
