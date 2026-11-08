@extends('layouts.metronic.app')

@section('title', $document->exists ? 'Edit draft' : 'New '.($document->type === 'quote' ? 'quote' : 'invoice'))

@section('content')
@php
    use App\Modules\Finance\Support\Money;
    $oldLines = old('lines');
    $initialLines = $oldLines ?? ($document->exists
        ? $document->lines->map(fn ($line) => [
            'description' => $line->description,
            'quantity' => $line->quantity_thousandths / 1000,
            'unit_price' => Money::input($line->unit_price),
            'account_id' => $line->account_id,
            'tax_id' => $line->tax_id,
            'fund_id' => $line->fund_id,
        ])->all()
        : [['description' => '', 'quantity' => 1, 'unit_price' => '', 'account_id' => '', 'tax_id' => '', 'fund_id' => '']]);
    $itemsJson = $items->map(fn ($item) => ['name' => $item->name, 'description' => $item->description ?: $item->name, 'price' => Money::input($item->unit_price), 'account_id' => $item->income_account_id])->values();
@endphp
<div class="flex flex-col gap-6" x-data="{
    lines: @js(array_values($initialLines)),
    items: @js($itemsJson),
    addItem(event) {
        const item = this.items[event.target.value];
        if (item) { this.lines.push({description: item.description, quantity: 1, unit_price: item.price, account_id: item.account_id || '', tax_id: '', fund_id: ''}); }
        event.target.value = '';
    },
    total() { return this.lines.reduce((sum, line) => sum + (parseFloat(line.quantity) || 0) * (parseFloat(line.unit_price) || 0), 0); },
}">
    <div>
        <h1 class="text-xl font-bold text-foreground">{{ $document->exists ? 'Edit draft' : 'New '.($document->type === 'quote' ? 'quote' : 'invoice') }}</h1>
        <p class="text-xs text-muted-foreground">Nothing is posted to the ledger until you issue it.</p>
    </div>

    @include('finance::partials.nav')

    <form method="POST" action="{{ $document->exists ? route('finance.invoices.update', $document->uuid) : route('finance.invoices.store') }}" class="kt-card">
        @csrf
        @if($document->exists) @method('PUT') @endif
        <input type="hidden" name="type" value="{{ old('type', $document->type) }}">
        <div class="kt-card-content flex flex-col gap-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <select class="kt-select" name="contact_id" required>
                    <option value="">Choose customer</option>
                    @foreach($contacts as $contact)
                        <option value="{{ $contact->id }}" @selected((int) old('contact_id', $document->contact_id) === $contact->id)>{{ $contact->name }}</option>
                    @endforeach
                </select>
                <input class="kt-input" type="date" name="issue_date" value="{{ old('issue_date', $document->issue_date?->toDateString() ?? today()->toDateString()) }}" required>
                <input class="kt-input" type="date" name="due_date" value="{{ old('due_date', $document->due_date?->toDateString()) }}" title="Due date (defaults to the payment terms)">
            </div>

            <div>
                <select class="kt-select md:w-72" @change="addItem($event)">
                    <option value="">Add from saved items…</option>
                    <template x-for="(item, index) in items" :key="index"><option :value="index" x-text="item.name + ' (' + item.price + ')'"></option></template>
                </select>
            </div>

            <template x-for="(line, index) in lines" :key="index">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-2">
                    <input class="kt-input md:col-span-4" :name="`lines[${index}][description]`" x-model="line.description" placeholder="Description" required>
                    <input class="kt-input md:col-span-1" type="number" step="0.001" min="0.001" :name="`lines[${index}][quantity]`" x-model="line.quantity" placeholder="Qty" required>
                    <input class="kt-input md:col-span-2" type="number" step="0.01" min="0" :name="`lines[${index}][unit_price]`" x-model="line.unit_price" placeholder="Price" required>
                    <select class="kt-select md:col-span-2" :name="`lines[${index}][account_id]`" x-model="line.account_id">
                        <option value="">Default income</option>
                        @foreach($incomeAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach
                    </select>
                    @if($taxes->isNotEmpty())
                    <select class="kt-select md:col-span-1" :name="`lines[${index}][tax_id]`" x-model="line.tax_id">
                        <option value="">No tax</option>
                        @foreach($taxes as $tax)<option value="{{ $tax->id }}">{{ $tax->name }}</option>@endforeach
                    </select>
                    @endif
                    @if($funds->isNotEmpty())
                    <select class="kt-select md:col-span-1" :name="`lines[${index}][fund_id]`" x-model="line.fund_id">
                        <option value="">No fund</option>
                        @foreach($funds as $fund)<option value="{{ $fund->id }}">{{ $fund->name }}</option>@endforeach
                    </select>
                    @endif
                    <button type="button" class="kt-btn kt-btn-sm kt-btn-outline" x-show="lines.length > 1" @click="lines.splice(index, 1)">×</button>
                </div>
            </template>

            <div class="flex items-center justify-between">
                <button type="button" class="kt-btn kt-btn-sm kt-btn-outline" @click="lines.push({description: '', quantity: 1, unit_price: '', account_id: '', tax_id: '', fund_id: ''})">Add line</button>
                <div class="text-sm font-semibold">Subtotal {{ $settings->base_currency }} <span x-text="total().toFixed(2)"></span></div>
            </div>

            <textarea class="kt-input" name="notes" rows="2" placeholder="Note to the customer">{{ old('notes', $document->notes) }}</textarea>
            <textarea class="kt-input" name="terms" rows="2" placeholder="Terms">{{ old('terms', $document->terms) }}</textarea>
            <div><button class="kt-btn kt-btn-primary" type="submit">Save draft</button></div>
        </div>
    </form>
</div>
@endsection
