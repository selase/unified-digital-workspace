@extends('layouts.metronic.app')

@section('title', __('locale.labels.invoices'))

@section('content')
    @php
        $invoice = $subscriptionInvoice ?? null;
        $tenant = $invoice?->tenant;
        $subscriptionTransaction = $subscription_transaction ?? null;
        $status = $invoice?->status ?? $invoice?->provider_status ?? 'unknown';
        $statusClass = match ($status) {
            'active', 'paid', 'succeeded' => 'kt-badge-success',
            'past_due', 'pending' => 'kt-badge-warning',
            'cancelled', 'canceled' => 'kt-badge-secondary',
            'failed' => 'kt-badge-destructive',
            default => 'kt-badge-secondary',
        };
        $issueDate = $invoice?->created_at;
        $paidDate = $invoice?->paid_at ?? $invoice?->created_at;
        $providerName = config('app.system_setting.provider.name');
        $providerAddress = config('app.system_setting.provider.address');
    @endphp

    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Subscription Invoice</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Invoice #{{ $invoice?->id ?? 'N/A' }}</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Review the payment and billing details.</p>
                </div>
                <button type="button" class="kt-btn kt-btn-outline" onclick="window.print();">Print</button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 rounded-xl border border-border bg-background p-6">
                <div class="grid gap-6 lg:grid-cols-2">
                    <div>
                        <div class="text-xs uppercase text-muted-foreground">Issue Date</div>
                        <div class="text-sm text-foreground">{{ $issueDate?->format('M d, Y') ?? 'N/A' }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-muted-foreground">Paid Date</div>
                        <div class="text-sm text-foreground">{{ $paidDate?->format('M d, Y') ?? 'N/A' }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-muted-foreground">Issued For</div>
                        <div class="text-sm text-foreground">{{ $tenant?->name ?? 'N/A' }}</div>
                        <div class="text-xs text-muted-foreground">{{ $tenant?->address }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-muted-foreground">Issued By</div>
                        <div class="text-sm text-foreground">{{ $providerName ?? 'N/A' }}</div>
                        <div class="text-xs text-muted-foreground">{{ $providerAddress }}</div>
                    </div>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th>Description</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Total Credit</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            <tr>
                                <td>{{ $invoice?->description ?? 'Payment for subscription' }}</td>
                                <td class="text-right">1</td>
                                <td class="text-right">{{ $invoice?->credit_balance ?? 'N/A' }}</td>
                                <td class="text-right font-semibold">
                                    {{ App\Libraries\Helper::formatAmountWithCurrencySymbol($invoice?->amount) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-end">
                    <div class="w-full max-w-xs space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-xs uppercase text-muted-foreground">Subtotal</span>
                            <span class="text-sm text-foreground">{{ App\Libraries\Helper::formatAmountWithCurrencySymbol($invoice?->amount) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs uppercase text-muted-foreground">VAT</span>
                            <span class="text-sm text-foreground">0.00</span>
                        </div>
                        <div class="flex items-center justify-between font-semibold">
                            <span class="text-xs uppercase text-muted-foreground">Total</span>
                            <span class="text-sm text-foreground">{{ App\Libraries\Helper::formatAmountWithCurrencySymbol($invoice?->amount) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-border bg-background p-6">
                <div class="flex items-center gap-2">
                    <span class="kt-badge {{ $statusClass }}">{{ strtoupper($status) }}</span>
                    <span class="text-xs text-muted-foreground">Invoice status</span>
                </div>

                <div class="mt-6 space-y-4 text-sm">
                    <div>
                        <div class="text-xs uppercase text-muted-foreground">Payment Method</div>
                        <div class="text-sm text-foreground">
                            {{ ucfirst(str_replace('_', ' ', $subscriptionTransaction?->payment_method ?? 'N/A')) }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-muted-foreground">Project Overview</div>
                        <div class="text-sm text-foreground">SaaS App Quickstarter</div>
                        <a href="#" class="text-xs text-primary">View Project</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('custom-scripts')
    <script src="{{ asset('js/scripts.js') }}"></script>
@endpush
