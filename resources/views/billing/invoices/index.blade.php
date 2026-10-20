@extends('layouts.metronic.app')

@section('title', 'Invoices')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-foreground">Invoices</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Issued invoices for {{ $tenant->name }}.</p>
                </div>
                <a href="{{ route('billing.index') }}" class="kt-btn kt-btn-outline">Back to billing</a>
            </div>
            <div class="mt-6 overflow-x-auto">
                <table class="kt-table table-auto kt-table-border">
                    <thead>
                        <tr>
                            <th scope="col">Invoice number</th>
                            <th scope="col">Period</th>
                            <th scope="col">Due</th>
                            <th scope="col">Amount</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invoices as $invoice)
                            <tr>
                                <td>{{ $invoice->number }}</td>
                                <td>{{ $invoice->period_start?->format('d/m/Y') ?? '—' }} – {{ $invoice->period_end?->format('d/m/Y') ?? '—' }}</td>
                                <td>{{ $invoice->due_at?->format('d/m/Y') ?? '—' }}</td>
                                <td>{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</td>
                                <td>{{ ucfirst($invoice->status) }}</td>
                                <td>
                                    <a href="{{ route('billing.invoices.show', $invoice->id) }}" class="kt-btn kt-btn-sm kt-btn-outline" aria-label="View invoice {{ $invoice->number }}">View</a>
                                    <a href="{{ route('billing.invoices.download', $invoice->id) }}" class="kt-btn kt-btn-sm kt-btn-outline" aria-label="Download invoice {{ $invoice->number }}">Download PDF</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-muted-foreground">No invoices issued yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-6">{{ $invoices->links() }}</div>
        </div>
    </section>
@endsection
