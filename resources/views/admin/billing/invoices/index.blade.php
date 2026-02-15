@extends('layouts.metronic.app')

@section('title', 'System Invoices')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Billing</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">System Invoices</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Issue and track invoices for tenant subscriptions.</p>
                </div>
                <a href="{{ route('admin.billing.invoices.create') }}" class="kt-btn kt-btn-primary">
                    <i class="ki-filled ki-plus text-base"></i>
                    Create Invoice
                </a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="flex flex-wrap items-end justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-foreground">Invoices</h2>
                    <p class="text-xs text-muted-foreground">Search, issue, and review billing periods.</p>
                </div>
                <form action="{{ route('admin.billing.invoices.index') }}" method="GET" class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-muted-foreground" for="invoice-search">Search</label>
                    <input id="invoice-search" type="text" name="search" value="{{ request('search') }}" class="kt-input w-56" placeholder="Search invoices" />
                </form>
            </div>

            <form id="bulkActionForm" action="{{ route('admin.billing.invoices.bulk-issue') }}" method="POST" class="space-y-4">
                @csrf
                <div class="flex justify-end" id="bulkActions">
                    <button type="button" id="bulkIssueBtn" class="kt-btn kt-btn-sm kt-btn-primary hidden">
                        Issue Selected
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="kt-table">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th class="w-10">
                                    <input class="kt-checkbox" type="checkbox" data-kt-check="true" data-kt-check-target="#bulkActionForm .row-check" value="1" />
                                </th>
                                <th>Invoice #</th>
                                <th>Tenant</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Period</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @foreach($invoices as $invoice)
                                <tr>
                                    <td>
                                        <input class="kt-checkbox row-check" type="checkbox" name="ids[]" value="{{ $invoice->id }}" {{ $invoice->status !== 'draft' ? 'disabled' : '' }} />
                                    </td>
                                    <td>
                                        <span class="font-medium text-foreground">{{ $invoice->number }}</span>
                                    </td>
                                    <td>
                                        <div class="flex flex-col">
                                            <a href="{{ route('tenants.edit', $invoice->tenant->uuid) }}" class="font-medium text-foreground hover:text-primary">
                                                {{ $invoice->tenant->name }}
                                            </a>
                                            <span class="text-xs text-muted-foreground">{{ $invoice->tenant->email }}</span>
                                        </div>
                                    </td>
                                    <td>${{ number_format((float)$invoice->total, 2) }}</td>
                                    <td>
                                        <span class="kt-badge {{ $invoice->status === 'paid' ? 'kt-badge-success' : ($invoice->status === 'draft' ? 'kt-badge-warning' : 'kt-badge-primary') }}">
                                            {{ strtoupper($invoice->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $invoice->period_start->format('M Y') }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.billing.invoices.show', $invoice->id) }}" class="kt-btn kt-btn-sm kt-btn-outline">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>

            <div class="mt-5">
                {{ $invoices->links() }}
            </div>
        </div>
    </section>
@endsection

@push('styles')
    <link href="{{ asset('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
@endpush

@push('vendor-scripts')
    <script src="{{ asset('assets/plugins/global/plugins.bundle.js') }}"></script>
@endpush

@push('custom-scripts')
    <script>
        const bulkIssueButton = document.getElementById('bulkIssueBtn');
        const bulkActionsWrapper = document.getElementById('bulkActions');

        function syncBulkActionsVisibility() {
            const checked = document.querySelectorAll('.row-check:checked').length;
            if (!bulkIssueButton) {
                return;
            }

            if (checked > 0) {
                bulkIssueButton.classList.remove('hidden');
                bulkActionsWrapper?.classList.remove('hidden');
            } else {
                bulkIssueButton.classList.add('hidden');
                bulkActionsWrapper?.classList.add('hidden');
            }
        }

        document.addEventListener('change', function (e) {
            if (e.target.classList.contains('row-check') || e.target.getAttribute('data-kt-check') === 'true') {
                syncBulkActionsVisibility();
            }
        });

        const mainCheck = document.querySelector('[data-kt-check="true"]');
        if (mainCheck) {
            mainCheck.addEventListener('change', function () {
                const targets = document.querySelectorAll(this.dataset.ktCheckTarget);
                targets.forEach(t => {
                    if (!t.disabled) {
                        t.checked = this.checked;
                    }
                });
                syncBulkActionsVisibility();
            });
        }

        bulkIssueButton?.addEventListener('click', function () {
            const count = document.querySelectorAll('.row-check:checked').length;
            Swal.fire({
                text: `Are you sure you want to issue ${count} selected invoices? They will become visible to the tenants immediately.`,
                icon: "warning",
                showCancelButton: true,
                buttonsStyling: false,
                confirmButtonText: "Yes, issue them!",
                cancelButtonText: "No, cancel",
                customClass: {
                    confirmButton: "kt-btn kt-btn-primary",
                    cancelButton: "kt-btn kt-btn-outline"
                }
            }).then(function (result) {
                if (result.isConfirmed) {
                    document.getElementById('bulkActionForm').submit();
                }
            });
        });

        bulkActionsWrapper?.classList.add('hidden');
    </script>
@endpush
