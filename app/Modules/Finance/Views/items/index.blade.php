@extends('layouts.metronic.app')

@section('title', 'Items')

@section('content')
@php use App\Modules\Finance\Support\Money; @endphp
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">Items</h1>
        <p class="text-xs text-muted-foreground">Products and services you can reuse on invoices.</p>
    </div>

    @include('finance::partials.nav')

    @can('finance.invoices.manage')
    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Add an item</h3></div>
        <form method="POST" action="{{ route('finance.items.store') }}" class="kt-card-content grid grid-cols-1 md:grid-cols-4 gap-3">
            @csrf
            <input class="kt-input" name="name" value="{{ old('name') }}" placeholder="Name" required>
            <input class="kt-input" type="number" step="0.01" min="0" name="unit_price" value="{{ old('unit_price') }}" placeholder="Price" required>
            <select class="kt-select" name="income_account_id">
                <option value="">Default income account</option>
                @foreach($incomeAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>
                @endforeach
            </select>
            <input class="kt-input" name="description" value="{{ old('description') }}" placeholder="Description">
            <div><button class="kt-btn kt-btn-primary" type="submit">Save item</button></div>
        </form>
    </div>
    @endcan

    <div class="kt-card">
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Name</th><th>Description</th><th class="text-end">Price</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($items as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td>{{ $item->description }}</td>
                        <td class="text-end">{{ Money::format($item->unit_price, '') }}</td>
                        <td>{{ $item->is_active ? 'Active' : 'Off' }}</td>
                        <td class="text-end">
                            @can('finance.invoices.manage')
                            @if($item->is_active)
                            <form method="POST" action="{{ route('finance.items.destroy', $item->uuid) }}">
                                @csrf @method('DELETE')
                                <button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">Switch off</button>
                            </form>
                            @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted-foreground py-6">No items yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
