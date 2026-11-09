@extends('layouts.metronic.app')

@section('title', $bill->exists ? 'Edit draft bill' : 'New bill')

@section('content')
@php
    use App\Modules\Finance\Support\Money;
    $initialLines = old('lines') ?? ($bill->exists
        ? $bill->lines->map(fn ($line) => ['description' => $line->description, 'amount' => Money::input($line->amount), 'account_id' => $line->account_id, 'fund_id' => $line->fund_id])->all()
        : [['description' => '', 'amount' => '', 'account_id' => '', 'fund_id' => '']]);
@endphp
<div class="flex flex-col gap-6" x-data="{
    lines: @js(array_values($initialLines)),
    total() { return this.lines.reduce((sum, line) => sum + (parseFloat(line.amount) || 0), 0); },
}">
    <div>
        <h1 class="text-xl font-bold text-foreground">{{ $bill->isCredit() ? 'Edit supplier credit note' : ($bill->exists ? 'Edit draft bill' : 'New bill') }}</h1>
        <p class="text-xs text-muted-foreground">Nothing is posted to the ledger until you approve it. Suppliers are contacts marked as "Supplier".</p>
    </div>

    @include('finance::partials.nav')

    <form method="POST" action="{{ $bill->exists ? route('finance.bills.update', $bill->uuid) : route('finance.bills.store') }}" class="kt-card">
        @csrf
        @if($bill->exists) @method('PUT') @endif
        <div class="kt-card-content flex flex-col gap-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <select class="kt-select" name="contact_id" required>
                    <option value="">Choose supplier</option>
                    @foreach($vendors as $vendor)
                        <option value="{{ $vendor->id }}" @selected((int) old('contact_id', $bill->contact_id) === $vendor->id)>{{ $vendor->name }}</option>
                    @endforeach
                </select>
                <input class="kt-input" name="vendor_reference" value="{{ old('vendor_reference', $bill->vendor_reference) }}" placeholder="Supplier's invoice no.">
                <input class="kt-input" type="date" name="issue_date" value="{{ old('issue_date', $bill->issue_date?->toDateString() ?? today()->toDateString()) }}" required>
                <input class="kt-input" type="date" name="due_date" value="{{ old('due_date', $bill->due_date?->toDateString()) }}" title="Due date (defaults to the supplier's payment terms)">
            </div>
            @if($vendors->isEmpty())
                <p class="text-xs text-muted-foreground">No suppliers yet. Add one under Contacts and tick "Supplier".</p>
            @endif

            <template x-for="(line, index) in lines" :key="index">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-2">
                    <input class="kt-input md:col-span-4" :name="`lines[${index}][description]`" x-model="line.description" placeholder="Description" required>
                    <input class="kt-input md:col-span-2" type="number" step="0.01" min="0.01" :name="`lines[${index}][amount]`" x-model="line.amount" placeholder="Amount" required>
                    <select class="kt-select md:col-span-3" :name="`lines[${index}][account_id]`" x-model="line.account_id" required>
                        <option value="">Expense account</option>
                        @foreach($expenseAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} {{ $account->name }}</option>@endforeach
                    </select>
                    @if($funds->isNotEmpty())
                    <select class="kt-select md:col-span-2" :name="`lines[${index}][fund_id]`" x-model="line.fund_id">
                        <option value="">No fund</option>
                        @foreach($funds as $fund)<option value="{{ $fund->id }}">{{ $fund->name }}</option>@endforeach
                    </select>
                    @endif
                    <button type="button" class="kt-btn kt-btn-sm kt-btn-outline" x-show="lines.length > 1" @click="lines.splice(index, 1)">×</button>
                </div>
            </template>

            <div class="flex items-center justify-between">
                <button type="button" class="kt-btn kt-btn-sm kt-btn-outline" @click="lines.push({description: '', amount: '', account_id: '', fund_id: ''})">Add line</button>
                <div class="text-sm font-semibold">Total {{ $currency }} <span x-text="total().toFixed(2)"></span></div>
            </div>

            <textarea class="kt-input" name="notes" rows="2" placeholder="Notes">{{ old('notes', $bill->notes) }}</textarea>
            <div><button class="kt-btn kt-btn-primary" type="submit">Save draft</button></div>
        </div>
    </form>
</div>
@endsection
