@extends('layouts.metronic.app')

@section('title', $bill->number ?? 'Draft bill')

@section('content')
@php use App\Modules\Finance\Support\Money; @endphp
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">{{ $bill->isCredit() ? 'Supplier credit note' : 'Bill' }} {{ $bill->number ?? '(draft)' }}</h1>
            <p class="text-xs text-muted-foreground">{{ $bill->contact->name }} · {{ $bill->isOverdue() ? 'Overdue' : str_replace('_', ' ', ucfirst($bill->status)) }}</p>
        </div>
        @can('finance.bills.manage')
        <div class="flex flex-wrap items-center gap-2">
            @if($bill->status === 'draft')
                <a href="{{ route('finance.bills.edit', $bill->uuid) }}" class="kt-btn kt-btn-sm kt-btn-outline">Edit</a>
                <form method="POST" action="{{ route('finance.bills.approve', $bill->uuid) }}">@csrf<button class="kt-btn kt-btn-sm kt-btn-primary" type="submit">Approve and post</button></form>
            @endif
            @if($bill->isOpen())
                <form method="POST" action="{{ route('finance.bills.credit', $bill->uuid) }}">@csrf<button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">Create credit note</button></form>
            @endif
            @if(in_array($bill->status, ['draft', 'approved', 'applied'], true) && ! ($bill->status === 'approved' && ! $bill->isCredit() && $bill->amount_paid > 0))
                <form method="POST" action="{{ route('finance.bills.void', $bill->uuid) }}" onsubmit="return confirm('{{ $bill->status === 'draft' ? 'Delete this draft?' : 'Void this bill?' }}')">@csrf<button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">{{ $bill->status === 'draft' ? 'Delete draft' : 'Void' }}</button></form>
            @endif
        </div>
        @endcan
    </div>

    @include('finance::partials.nav')

    <div class="kt-card">
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
            <div><div class="text-xs text-muted-foreground">Supplier</div><div>{{ $bill->contact->name }}</div><div class="text-muted-foreground">{{ $bill->contact->email }}</div></div>
            <div><div class="text-xs text-muted-foreground">Supplier's invoice no.</div><div>{{ $bill->vendor_reference ?: '—' }}</div></div>
            <div><div class="text-xs text-muted-foreground">Issued</div><div>{{ $bill->issue_date->toInstitutionalDate() }}</div></div>
            <div><div class="text-xs text-muted-foreground">Due</div><div>{{ $bill->due_date?->toInstitutionalDate() ?? '—' }}</div></div>
        </div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Description</th><th>Account</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                @foreach($bill->lines as $line)
                    <tr><td>{{ $line->description }}</td><td>{{ $line->account->code }} {{ $line->account->name }}</td><td class="text-end">{{ Money::format($line->amount, $currency) }}</td></tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr><td colspan="2" class="text-end font-semibold">Total</td><td class="text-end font-semibold">{{ Money::format($bill->total, $currency) }}</td></tr>
                    <tr><td colspan="2" class="text-end">Paid</td><td class="text-end">{{ Money::format($bill->amount_paid, $currency) }}</td></tr>
                    @if($bill->credits_applied > 0)<tr><td colspan="2" class="text-end">Credit notes applied</td><td class="text-end">{{ Money::format($bill->credits_applied, $currency) }}</td></tr>@endif
                    <tr><td colspan="2" class="text-end font-semibold">Balance due</td><td class="text-end font-semibold">{{ Money::format($bill->balanceDue(), $currency) }}</td></tr>
                </tfoot>
            </table>
        </div>
    </div>

    @can('finance.bills.manage')
    @if($bill->isOpen())
    <form method="POST" action="{{ route('finance.bills.pay', $bill->uuid) }}" class="kt-card">
        @csrf
        <div class="kt-card-header"><h3 class="kt-card-title">Record a payment</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-4 gap-3">
            <input class="kt-input" type="date" name="date" value="{{ old('date', today()->toDateString()) }}" required>
            <input class="kt-input" type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', Money::input($bill->balanceDue())) }}" required>
            <select class="kt-select" name="method" required>
                @foreach(['bank_transfer' => 'Bank transfer', 'mobile_money' => 'Mobile money', 'cash' => 'Cash', 'cheque' => 'Cheque', 'card' => 'Card', 'other' => 'Other'] as $key => $label)
                    <option value="{{ $key }}" @selected(old('method') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <select class="kt-select" name="paid_from_account_id" required>
                <option value="">Paid from</option>
                @foreach($paymentAccounts as $account)<option value="{{ $account->id }}" @selected((int) old('paid_from_account_id') === $account->id)>{{ $account->name }}</option>@endforeach
            </select>
            <input class="kt-input md:col-span-3" name="reference" value="{{ old('reference') }}" placeholder="Reference (transfer ID, cheque no.)">
            <div><button class="kt-btn kt-btn-primary" type="submit">Record payment</button></div>
        </div>
    </form>
    @endif
    @endcan

    @if($bill->payments->isNotEmpty())
    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Payments</h3></div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Number</th><th>Date</th><th>Method</th><th>Reference</th><th class="text-end">Amount</th><th></th></tr></thead>
                <tbody>
                @foreach($bill->payments as $payment)
                    <tr class="{{ $payment->voided_at ? 'text-muted-foreground line-through' : '' }}">
                        <td>{{ $payment->number }}</td>
                        <td>{{ $payment->date->toInstitutionalDate() }}</td>
                        <td>{{ str_replace('_', ' ', ucfirst($payment->method)) }}</td>
                        <td>{{ $payment->reference }}</td>
                        <td class="text-end">{{ Money::format($payment->amount, $currency) }}</td>
                        <td class="text-end">
                            @can('finance.bills.manage')
                            @unless($payment->voided_at)
                            <form method="POST" action="{{ route('finance.bills.payments.void', $payment->uuid) }}" onsubmit="return confirm('Void this payment?')">@csrf<button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">Void</button></form>
                            @endunless
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
