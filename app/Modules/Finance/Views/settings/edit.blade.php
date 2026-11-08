@extends('layouts.metronic.app')

@section('title', 'Finance settings')

@section('content')
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">Finance settings</h1>
        <p class="text-xs text-muted-foreground">Your business details, tax, funds and online payments.</p>
    </div>

    @include('finance::partials.nav')

    <form method="POST" action="{{ route('finance.settings.update') }}" class="kt-card">
        @csrf @method('PUT')
        <div class="kt-card-header"><h3 class="kt-card-title">Business and invoicing</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-3 gap-3">
            <input class="kt-input" name="legal_name" value="{{ old('legal_name', $settings->legal_name) }}" placeholder="Legal name">
            <input class="kt-input" name="tax_number" value="{{ old('tax_number', $settings->tax_number) }}" placeholder="TIN">
            <input class="kt-input" type="email" name="email" value="{{ old('email', $settings->email) }}" placeholder="Billing email">
            <input class="kt-input" name="phone" value="{{ old('phone', $settings->phone) }}" placeholder="Phone">
            <input class="kt-input" name="base_currency" maxlength="3" value="{{ old('base_currency', $settings->base_currency) }}" @readonly($hasPostings) title="{{ $hasPostings ? 'The currency cannot change after the first posting.' : 'Three-letter code' }}" required>
            <select class="kt-select" name="fiscal_year_start_month">
                @foreach(range(1, 12) as $month)<option value="{{ $month }}" @selected((int) old('fiscal_year_start_month', $settings->fiscal_year_start_month) === $month)>Year starts {{ date('F', mktime(0, 0, 0, $month, 1)) }}</option>@endforeach
            </select>
            <input class="kt-input" type="number" min="0" max="365" name="default_payment_terms_days" value="{{ old('default_payment_terms_days', $settings->default_payment_terms_days) }}" title="Default days until an invoice is due" required>
            <input class="kt-input" type="date" name="lock_date" value="{{ old('lock_date', $settings->lock_date?->toDateString()) }}" title="Nothing can be posted on or before this date">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="kt-checkbox" name="tax_enabled" value="1" @checked(old('tax_enabled', $settings->tax_enabled))> Charge tax on invoices (VAT-registered)</label>
            <textarea class="kt-input md:col-span-3" name="address" rows="2" placeholder="Address">{{ old('address', $settings->address) }}</textarea>
            <textarea class="kt-input md:col-span-3" name="invoice_footer" rows="2" placeholder="Default terms shown on invoices">{{ old('invoice_footer', $settings->invoice_footer) }}</textarea>
            <div><button class="kt-btn kt-btn-primary" type="submit">Save settings</button></div>
        </div>
    </form>

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Tax rates</h3></div>
        <div class="kt-card-content flex flex-col gap-3">
            @forelse($taxes as $tax)
                <div class="flex items-center justify-between rounded-lg bg-muted/30 p-3 text-sm">
                    <span>{{ $tax->name }} · {{ rtrim(rtrim(number_format($tax->rate_basis_points / 100, 2), '0'), '.') }}%</span>
                    <form method="POST" action="{{ route('finance.settings.taxes.toggle', $tax->uuid) }}">@csrf<button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">{{ $tax->is_active ? 'Switch off' : 'Switch on' }}</button></form>
                </div>
            @empty
                <p class="text-sm text-muted-foreground">No tax rates yet. Tax stays off until you are VAT-registered.</p>
            @endforelse
            <form method="POST" action="{{ route('finance.settings.taxes.ghana') }}">@csrf<button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">Add Ghana VAT + NHIL + GETFund (20%)</button></form>
        </div>
    </div>

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Funds</h3></div>
        <div class="kt-card-content flex flex-col gap-3">
            <p class="text-xs text-muted-foreground">Tag invoice and journal lines to a fund to track restricted money, such as grants.</p>
            @foreach($funds as $fund)
                <div class="rounded-lg bg-muted/30 p-3 text-sm">{{ $fund->code }} · {{ $fund->name }} {{ $fund->is_restricted ? '(restricted)' : '' }}</div>
            @endforeach
            <form method="POST" action="{{ route('finance.settings.funds.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                @csrf
                <input class="kt-input" name="code" placeholder="Code" required>
                <input class="kt-input" name="name" placeholder="Fund name" required>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="kt-checkbox" name="is_restricted" value="1"> Restricted</label>
                <button class="kt-btn kt-btn-outline" type="submit">Add fund</button>
            </form>
        </div>
    </div>

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Online payments (Paystack)</h3></div>
        <div class="kt-card-content flex flex-col gap-2 text-sm">
            @if($paystackReady)
                <p class="text-success">Paystack is connected. Invoices show a payment link that customers can use.</p>
            @else
                <p class="text-muted-foreground">Add your Paystack keys to enable payment links. Until then, record payments by hand.</p>
            @endif
            <p class="text-xs text-muted-foreground">If this Paystack account is not the UDW platform account, add this webhook URL in the Paystack dashboard:</p>
            <input class="kt-input" readonly value="{{ $webhookUrl }}" onclick="this.select()">
        </div>
    </div>
</div>
@endsection
