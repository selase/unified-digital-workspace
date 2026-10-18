@extends('layouts.metronic.app')

@section('title', 'Official Payment Receipt - ' . ($installment->payment_reference ?? 'REC-' . $installment->id))

@section('content')
<style>
@media print {
    .no-print {
        display: none !important;
    }
    body {
        background: white !important;
        color: black !important;
    }
    .kt-card {
        border: 1px solid #ccc !important;
        box-shadow: none !important;
    }
}
</style>

<div class="max-w-3xl mx-auto space-y-6">
    {{-- Action Bar (Hidden when printing) --}}
    <div class="no-print flex items-center justify-between gap-4">
        <a href="{{ url()->previous(route('real-estate.index')) }}" class="kt-btn kt-btn-outline kt-btn-sm">
            <i class="ki-filled ki-arrow-left text-xs"></i>
            Back
        </a>
        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="kt-btn kt-btn-primary kt-btn-sm">
                <i class="ki-filled ki-printer text-xs"></i>
                Print Official Receipt
            </button>
        </div>
    </div>

    {{-- Printable Receipt Card --}}
    <div class="kt-card p-8 bg-card border border-border shadow-md rounded-xl space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-border pb-6">
            <div>
                <div class="text-xs uppercase tracking-widest text-primary font-bold">Official Payment Receipt</div>
                <h1 class="text-2xl font-black text-foreground mt-1">{{ $tenant->name ?? 'Real Estate & Land Management' }}</h1>
                <p class="text-xs text-muted-foreground mt-0.5">Cadastral Land Administration &amp; Title Verification Services</p>
            </div>
            <div class="sm:text-end">
                <div class="inline-block px-3 py-1 rounded bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-300 font-mono text-xs font-bold uppercase">
                    Payment Verified
                </div>
                <div class="text-xs text-muted-foreground mt-1">Ref: <span class="font-mono font-semibold text-foreground">{{ $installment->payment_reference ?? 'N/A' }}</span></div>
                <div class="text-xs text-muted-foreground">Date: {{ $installment->paid_at ? $installment->paid_at->format('d M Y, H:i') : now()->format('d M Y') }}</div>
            </div>
        </div>

        {{-- Meta Grid: Client & Property Info --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-muted/20 p-4 rounded-lg border border-border">
            <div class="space-y-1">
                <div class="text-[10px] uppercase font-bold text-muted-foreground tracking-wider">Client / Purchaser</div>
                <div class="text-sm font-bold text-foreground">{{ $client?->first_name }} {{ $client?->last_name }}</div>
                <div class="text-xs text-muted-foreground">{{ $client?->email }}</div>
                <div class="text-xs font-mono text-muted-foreground pt-1">Allocation Ref: <span class="text-foreground font-semibold">{{ $allocation->allocation_number }}</span></div>
            </div>
            <div class="space-y-1">
                <div class="text-[10px] uppercase font-bold text-muted-foreground tracking-wider">Allocated Property Details</div>
                <div class="text-sm font-bold text-foreground">Plot {{ $parcel?->plot_number }} @if($parcel?->block_number) &bull; Block {{ $parcel->block_number }} @endif</div>
                <div class="text-xs text-muted-foreground">{{ $project?->name ?? 'Enclave' }} &bull; {{ $project?->location ?? 'Ghana' }}</div>
                <div class="text-xs text-muted-foreground">Tenure: <span class="font-semibold uppercase text-foreground">{{ $parcel?->tenure_type }}</span> &bull; Dimension: {{ $parcel?->size_dimension }}</div>
            </div>
        </div>

        {{-- Payment Breakdown Table --}}
        <div class="border border-border rounded-lg overflow-hidden">
            <table class="w-full text-xs">
                <thead class="bg-muted/40 text-muted-foreground uppercase text-[10px] font-semibold border-b border-border">
                    <tr>
                        <th class="py-2.5 px-4 text-start">Description</th>
                        <th class="py-2.5 px-4 text-center">Due Date</th>
                        <th class="py-2.5 px-4 text-center">Status</th>
                        <th class="py-2.5 px-4 text-end">Amount Paid</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr>
                        <td class="py-3 px-4">
                            <div class="font-bold text-foreground">Installment Schedule #{{ $installment->installment_number }}</div>
                            <div class="text-[11px] text-muted-foreground">Scheduled contract installment for Plot {{ $parcel?->plot_number }}</div>
                        </td>
                        <td class="py-3 px-4 text-center font-mono text-muted-foreground">
                            {{ $installment->due_date ? $installment->due_date->format('d/m/Y') : 'N/A' }}
                        </td>
                        <td class="py-3 px-4 text-center">
                            <span class="kt-badge kt-badge-xs kt-badge-success uppercase">{{ $installment->status }}</span>
                        </td>
                        <td class="py-3 px-4 text-end font-mono font-bold text-foreground text-sm">
                            {{ $installment->formattedAmountPaid() }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Totals Summary --}}
        <div class="flex flex-col sm:flex-row justify-between items-center gap-4 bg-muted/10 p-4 rounded-lg border border-border">
            <div class="text-xs text-muted-foreground space-y-0.5">
                <div>Total Allocation Price: <span class="font-semibold text-foreground">{{ $allocation->formattedTotalPrice() }}</span></div>
                <div>Total Paid to Date: <span class="font-semibold text-emerald-600">{{ $allocation->formattedAmountPaid() }}</span></div>
                <div>Remaining Balance: <span class="font-semibold text-rose-600">{{ $allocation->formattedOutstandingBalance() }}</span></div>
            </div>
            <div class="text-end">
                <div class="text-[10px] uppercase font-bold text-muted-foreground">Amount Credited</div>
                <div class="text-2xl font-black text-emerald-600 font-mono">{{ $installment->formattedAmountPaid() }}</div>
            </div>
        </div>

        {{-- Footer & Verification Seal --}}
        <div class="pt-6 border-t border-border flex flex-col sm:flex-row justify-between items-center gap-4 text-[11px] text-muted-foreground">
            <div>
                <p class="font-semibold text-foreground">Ghana Land Compliance &amp; Escrow Verification</p>
                <p>This is a computer-generated official receipt issued by {{ $tenant->name ?? 'the estate administration' }}.</p>
            </div>
            <div class="text-center sm:text-end border border-dashed border-border px-3 py-2 rounded">
                <div class="font-bold font-mono text-[10px] text-foreground uppercase tracking-widest">AUTHENTICATED</div>
                <div class="text-[9px] text-muted-foreground">SECURE LEDGER VALIDATED</div>
            </div>
        </div>
    </div>
</div>
@endsection

