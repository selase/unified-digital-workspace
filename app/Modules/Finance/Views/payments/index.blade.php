@extends('layouts.metronic.app')

@section('title', 'Payments')

@section('content')
@php
    use App\Modules\Finance\Support\Money;
    $invoicesByContact = $openInvoices->map(fn ($group) => $group->map(fn ($invoice) => [
        'id' => $invoice->id, 'uuid' => $invoice->uuid, 'number' => $invoice->number, 'balance' => Money::input($invoice->balanceDue()),
    ])->values());
    $preselect = $preselectedInvoice ? $openInvoices->flatten()->firstWhere('uuid', $preselectedInvoice) : null;
@endphp
<div class="flex flex-col gap-6" x-data="{
    contact: '{{ old('contact_id', $preselect?->contact_id) }}',
    invoices: @js($invoicesByContact),
    amount: '{{ old('amount', $preselect ? Money::input($preselect->balanceDue()) : '') }}',
    allocations: @js(old('allocations', $preselect ? [$preselect->id => Money::input($preselect->balanceDue())] : [])),
    open() { return this.invoices[this.contact] || []; },
}">
    <div>
        <h1 class="text-xl font-bold text-foreground">Payments</h1>
        <p class="text-xs text-muted-foreground">Money received from customers. Anything not applied to an invoice stays as customer credit.</p>
    </div>

    @include('finance::partials.nav')

    @can('finance.payments.manage')
    <form method="POST" action="{{ route('finance.payments.store') }}" class="kt-card">
        @csrf
        <div class="kt-card-header"><h3 class="kt-card-title">Record a payment</h3></div>
        <div class="kt-card-content flex flex-col gap-3">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <select class="kt-select" name="contact_id" x-model="contact" required>
                    <option value="">Customer</option>
                    @foreach($contacts as $contact)<option value="{{ $contact->id }}">{{ $contact->name }}</option>@endforeach
                </select>
                <input class="kt-input" type="date" name="date" value="{{ old('date', today()->toDateString()) }}" required>
                <input class="kt-input" type="number" step="0.01" min="0.01" name="amount" x-model="amount" placeholder="Amount" required>
                <select class="kt-select" name="method" required>
                    @foreach(['bank_transfer' => 'Bank transfer', 'mobile_money' => 'Mobile money', 'cash' => 'Cash', 'cheque' => 'Cheque', 'card' => 'Card', 'other' => 'Other'] as $key => $label)
                        <option value="{{ $key }}" @selected(old('method') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <select class="kt-select" name="deposit_account_id">
                    <option value="">Deposit to Bank</option>
                    @foreach($depositAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach
                </select>
                <input class="kt-input md:col-span-3" name="reference" value="{{ old('reference') }}" placeholder="Reference (transfer ID, receipt no.)">
            </div>

            <template x-if="open().length">
                <div class="rounded-lg bg-muted/30 p-3 flex flex-col gap-2">
                    <div class="text-xs text-muted-foreground">Apply to open invoices</div>
                    <template x-for="invoice in open()" :key="invoice.id">
                        <div class="flex items-center gap-3">
                            <span class="w-40 text-sm" x-text="invoice.number"></span>
                            <span class="w-32 text-xs text-muted-foreground" x-text="'Balance ' + invoice.balance"></span>
                            <input class="kt-input w-40" type="number" step="0.01" min="0" :name="`allocations[${invoice.id}]`" x-model="allocations[invoice.id]" placeholder="0.00">
                        </div>
                    </template>
                </div>
            </template>

            <textarea class="kt-input" name="notes" rows="2" placeholder="Notes">{{ old('notes') }}</textarea>
            <div><button class="kt-btn kt-btn-primary" type="submit">Record payment</button></div>
        </div>
    </form>
    @endcan

    <div class="kt-card">
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Number</th><th>Date</th><th>Customer</th><th>Method</th><th>Reference</th><th class="text-end">Amount</th><th></th></tr></thead>
                <tbody>
                @forelse($payments as $payment)
                    <tr>
                        <td>{{ $payment->number }}</td>
                        <td>{{ $payment->date->toInstitutionalDate() }}</td>
                        <td>{{ $payment->contact->name }}</td>
                        <td>{{ str_replace('_', ' ', ucfirst($payment->method)) }}</td>
                        <td>{{ $payment->reference }}</td>
                        <td class="text-end">{{ Money::format($payment->amount, '') }}</td>
                        <td class="text-end">
                            @can('finance.payments.manage')
                            <form method="POST" action="{{ route('finance.payments.void', $payment->uuid) }}" onsubmit="return confirm('Void this payment? The invoices it paid will be re-opened.')">
                                @csrf<button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">Void</button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted-foreground py-6">No payments yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $payments->links() }}
</div>
@endsection
