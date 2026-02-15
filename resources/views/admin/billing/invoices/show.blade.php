@extends('layouts.metronic.app')

@section('title', 'Invoice Details: ' . $invoice->number)

@section('content')
    @php
        $lineItemCount = $invoice->items->count();
        $adjustmentCount = $invoice->items->filter(fn ($item) => ($item->meta['type'] ?? null) === 'adjustment')->count();
        $taxLineCount = is_array($invoice->tax_details) ? count($invoice->tax_details) : 0;
    @endphp

    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Billing</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Invoice #{{ $invoice->number }}</h1>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="kt-badge {{ $invoice->status === 'draft' ? 'kt-badge-warning' : ($invoice->status === 'paid' ? 'kt-badge-success' : 'kt-badge-primary') }}">
                            {{ strtoupper($invoice->status) }}
                        </span>
                        <span class="text-xs text-muted-foreground">Due by {{ $invoice->due_at->format('M d, Y') }}</span>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    @if($invoice->status === 'draft')
                        <form id="issueInvoiceForm" action="{{ route('admin.billing.invoices.issue', $invoice) }}" method="POST">
                            @csrf
                            <button type="button" class="kt-btn kt-btn-primary" id="issueInvoiceBtn">
                                Issue Invoice
                            </button>
                        </form>
                    @endif

                    <div class="kt-menu kt-menu-default" data-kt-menu="true">
                        <div class="kt-menu-item" data-kt-menu-item-offset="0, 10px" data-kt-menu-item-placement="bottom-end" data-kt-menu-item-toggle="dropdown" data-kt-menu-item-trigger="click">
                            <button type="button" class="kt-menu-toggle kt-btn kt-btn-outline">
                                Actions
                                <span class="kt-menu-arrow">
                                    <i class="ki-filled ki-down text-xs"></i>
                                </span>
                            </button>
                            <div class="kt-menu-dropdown w-48 py-2">
                                @if($invoice->status !== \App\Models\Invoice::STATUS_DRAFT)
                                    <div class="kt-menu-item">
                                        <form id="resendInvoiceForm" action="{{ route('admin.billing.invoices.resend', $invoice) }}" method="POST">
                                            @csrf
                                            <button type="button" id="resendInvoiceBtn" class="kt-menu-link w-full text-left">Resend Email</button>
                                        </form>
                                    </div>
                                @endif
                                <div class="kt-menu-item">
                                    <a href="{{ route('admin.billing.invoices.download', $invoice) }}" class="kt-menu-link">Download PDF</a>
                                </div>
                                <div class="kt-menu-item">
                                    <button type="button" class="kt-menu-link w-full text-left" onclick="window.print();">Print Invoice</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <a href="{{ route('admin.billing.invoices.index') }}" class="kt-btn kt-btn-outline">Back to List</a>
                </div>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-3">
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Line Items</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $lineItemCount }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Adjustments / Credits</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $adjustmentCount }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Tax Lines</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $taxLineCount }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="grid gap-6 lg:grid-cols-3">
                <div>
                    <p class="text-xs uppercase text-muted-foreground">Billed To</p>
                    <div class="mt-2 text-sm font-semibold text-foreground">{{ $invoice->tenant->name }}</div>
                    <div class="text-xs text-muted-foreground">{{ $invoice->tenant->email }}</div>
                </div>
                <div>
                    <p class="text-xs uppercase text-muted-foreground">Period</p>
                    <div class="mt-2 text-sm font-semibold text-foreground">
                        {{ $invoice->period_start->format('M d, Y') }} - {{ $invoice->period_end->format('M d, Y') }}
                    </div>
                </div>
                <div class="lg:text-right">
                    <p class="text-xs uppercase text-muted-foreground">Amount Due</p>
                    <div class="mt-2 text-2xl font-semibold text-foreground">${{ number_format((float)$invoice->total, 2) }}</div>
                    <div class="text-xs text-muted-foreground">Due by {{ $invoice->due_at->format('M d, Y') }}</div>
                </div>
            </div>

            <div class="my-6 border-t border-border"></div>

            <div class="overflow-x-auto">
                <table class="kt-table">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>Description</th>
                            <th class="text-center">Qty</th>
                            <th class="text-center">Unit Price</th>
                            <th class="text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-foreground">
                        @foreach($invoice->items as $item)
                            <tr>
                                <td>
                                    <div class="flex flex-col">
                                        <span class="font-medium text-foreground">{{ $item->description }}</span>
                                        @if($item->metric)
                                            <span class="text-xs text-muted-foreground">Metric: {{ $item->metric->name }}</span>
                                        @endif
                                        @if($item->meta && isset($item->meta['type']) && $item->meta['type'] === 'adjustment')
                                            <form action="{{ route('admin.billing.invoices.items.destroy', $item) }}" method="POST" class="mt-1 delete-adjustment-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="text-xs text-destructive hover:underline remove-adj-btn">Remove Adjustment</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-center">{{ number_format((float)$item->quantity, 2) }}</td>
                                <td class="text-center">${{ number_format((float)$item->unit_price, 4) }}</td>
                                <td class="text-right font-semibold">${{ number_format((float)$item->subtotal, 2) }}</td>
                            </tr>
                        @endforeach

                        @if($invoice->status === 'draft')
                            <tr>
                                <td colspan="4">
                                    <button type="button" class="kt-btn kt-btn-outline w-full" data-kt-modal-toggle="#add_adjustment_modal">
                                        <i class="ki-filled ki-plus text-base"></i>
                                        Add Manual Adjustment / Credit
                                    </button>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                    <tfoot class="text-sm">
                        <tr>
                            <td colspan="3" class="text-right font-semibold text-muted-foreground">Subtotal</td>
                            <td class="text-right font-semibold">${{ number_format((float)$invoice->subtotal, 2) }}</td>
                        </tr>
                        @if($invoice->tax_details)
                            @foreach($invoice->tax_details as $tax)
                                <tr>
                                    <td colspan="3" class="text-right text-xs text-muted-foreground">{{ $tax['name'] }} ({{ $tax['rate'] }}%)</td>
                                    <td class="text-right text-xs text-muted-foreground">${{ number_format((float)$tax['amount'], 2) }}</td>
                                </tr>
                            @endforeach
                        @endif
                        <tr class="border-t border-border">
                            <td colspan="3" class="text-right text-base font-semibold">Total</td>
                            <td class="text-right text-base font-semibold">${{ number_format((float)$invoice->total, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </section>
@endsection

@push('modals')
    <div class="kt-modal" data-kt-modal="true" id="add_adjustment_modal">
        <div class="kt-modal-content max-w-96 top-9">
            <div class="kt-modal-header">
                <h3 class="kt-modal-title">Add Adjustment</h3>
                <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost shrink-0" data-kt-modal-dismiss="true">
                    <i class="ki-filled ki-cross"></i>
                </button>
            </div>
            <form action="{{ route('admin.billing.invoices.adjust', $invoice) }}" method="POST" class="kt-form">
                @csrf
                <div class="kt-modal-body p-6 space-y-4">
                    <div class="kt-form-item">
                        <label class="kt-form-label">Description <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <input type="text" name="description" class="kt-input" placeholder="e.g. Service Credit" required>
                        </div>
                    </div>
                    <div class="kt-form-item">
                        <label class="kt-form-label">Amount ($) <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <input type="number" step="0.01" name="amount" class="kt-input" placeholder="0.00" required>
                            <p class="mt-2 text-xs text-muted-foreground">Use negative values for discounts/credits.</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-border p-4">
                    <button type="button" class="kt-btn kt-btn-outline" data-kt-modal-dismiss="true">Cancel</button>
                    <button type="submit" class="kt-btn kt-btn-primary">Add Row</button>
                </div>
            </form>
        </div>
    </div>
@endpush

@push('custom-scripts')
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

        document.getElementById('issueInvoiceBtn')?.addEventListener('click', function () {
            runConfirmDialog({
                text: "Ready to issue this invoice? It will become visible to the tenant and an email notification will be sent.",
                icon: "warning",
                showCancelButton: true,
                buttonsStyling: false,
                confirmButtonText: "Yes, issue it!",
                cancelButtonText: "No, cancel",
                customClass: {
                    confirmButton: "kt-btn kt-btn-primary",
                    cancelButton: "kt-btn kt-btn-outline"
                }
            }, function () {
                document.getElementById('issueInvoiceForm').submit();
            });
        });

        document.getElementById('resendInvoiceBtn')?.addEventListener('click', function () {
            runConfirmDialog({
                text: "Are you sure you want to resend the invoice email to the tenant?",
                icon: "info",
                showCancelButton: true,
                buttonsStyling: false,
                confirmButtonText: "Yes, resend it!",
                cancelButtonText: "No, cancel",
                customClass: {
                    confirmButton: "kt-btn kt-btn-primary",
                    cancelButton: "kt-btn kt-btn-outline"
                }
            }, function () {
                document.getElementById('resendInvoiceForm').submit();
            });
        });

        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-adj-btn')) {
                const form = e.target.closest('.delete-adjustment-form');
                runConfirmDialog({
                    text: "Are you sure you want to remove this adjustment line item?",
                    icon: "warning",
                    showCancelButton: true,
                    buttonsStyling: false,
                    confirmButtonText: "Yes, remove it!",
                    cancelButtonText: "No, cancel",
                    customClass: {
                        confirmButton: "kt-btn kt-btn-danger",
                        cancelButton: "kt-btn kt-btn-outline"
                    }
                }, function () {
                    form.submit();
                });
            }
        });
    </script>
@endpush
