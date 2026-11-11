@extends('layouts.metronic.app')

@section('title', 'Stock')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <div><h1 class="text-xl font-bold text-foreground">Stock</h1><p class="text-xs text-muted-foreground">Items you keep track of. Issuing an invoice takes stock out, a credit note puts it back, and voiding either undoes it. To track an item, tick "Keep track of stock" when adding it under Items.</p></div>
        <a class="kt-btn kt-btn-sm kt-btn-outline" href="{{ route('finance.stock.index', ['export' => 'csv']) }}">Export CSV</a>
    </div>

    @include('finance::partials.nav')

    <div class="kt-card"><div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
        <thead><tr><th>Item</th><th>SKU</th><th class="text-end">On hand</th><th class="text-end">Reorder at</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($levels as $row)
            <tr>
                <td><a class="text-primary" href="{{ route('finance.stock.show', $row['item']->uuid) }}">{{ $row['item']->name }}</a></td>
                <td>{{ $row['item']->sku }}</td>
                <td class="text-end">{{ number_format($row['on_hand'] / 1000, 3) }}</td>
                <td class="text-end">{{ number_format($row['item']->reorder_level_thousandths / 1000, 3) }}</td>
                <td class="{{ $row['low'] ? 'text-destructive' : '' }}">{{ $row['low'] ? 'Reorder' : 'OK' }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted-foreground py-6">No items are tracked yet.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
</div>
@endsection
