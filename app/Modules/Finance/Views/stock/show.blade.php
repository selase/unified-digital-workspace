@extends('layouts.metronic.app')

@section('title', $item->name.' stock')

@section('content')
<div class="flex flex-col gap-6">
    <div><h1 class="text-xl font-bold text-foreground">{{ $item->name }}</h1><p class="text-xs text-muted-foreground">{{ $item->sku }} · {{ number_format($onHand / 1000, 3) }} on hand</p></div>

    @include('finance::partials.nav')
    @if(session('success'))<div class="rounded-lg bg-green-50 p-3 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="rounded-lg bg-red-50 p-3 text-sm text-red-700"><ul class="list-disc pl-5">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif

    @can('finance.invoices.manage')
    @if($item->track_stock)
    <form method="POST" action="{{ route('finance.stock.store', $item->uuid) }}" class="kt-card">@csrf
        <div class="kt-card-header"><h3 class="kt-card-title">Receive stock or adjust</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-5 gap-3">
            <select class="kt-select" name="kind"><option value="purchase">Goods received</option><option value="opening">Opening stock</option><option value="adjustment">Adjustment (+ or −)</option></select>
            <input class="kt-input" type="number" step="0.001" name="quantity" value="{{ old('quantity') }}" placeholder="Quantity" required>
            <input class="kt-input" type="date" name="moved_on" value="{{ old('moved_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
            <input class="kt-input" name="note" value="{{ old('note') }}" placeholder="Note, e.g. delivery from supplier">
            <div><button class="kt-btn kt-btn-primary" type="submit">Save</button></div>
        </div>
    </form>
    @endif
    @endcan

    <div class="kt-card"><div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
        <thead><tr><th>Date</th><th>What happened</th><th>Note</th><th class="text-end">Quantity</th></tr></thead>
        <tbody>
        @forelse($movements as $movement)
            <tr><td>{{ $movement->moved_on->toInstitutionalDate() }}</td><td>{{ $kinds[$movement->kind] ?? $movement->kind }}</td><td>{{ $movement->note }}</td><td class="text-end {{ $movement->quantity_thousandths < 0 ? 'text-destructive' : '' }}">{{ number_format($movement->quantity_thousandths / 1000, 3) }}</td></tr>
        @empty
            <tr><td colspan="4" class="text-center text-muted-foreground py-6">No movements yet.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
</div>
@endsection
