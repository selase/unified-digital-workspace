@extends('layouts.metronic.app')

@section('title', 'Store Orders Register')

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="Store Orders Register"
        description="Comprehensive audit register of all staff and client product orders with itemized billing."
    >
        <x-slot:actions>
            <a class="kt-btn kt-btn-outline" href="{{ route('store.index') }}">Back to Store</a>
        </x-slot:actions>
    </x-metronic.page-header>

    <div class="kt-card p-6">
        <div class="overflow-x-auto">
            <table class="kt-table table-auto kt-table-border w-full text-sm">
                <thead>
                    <tr class="border-b border-border/60 text-left text-xs font-semibold text-muted-foreground">
                        <th class="py-3 px-4">Order Ref</th>
                        <th class="py-3 px-4">Customer Details</th>
                        <th class="py-3 px-4">Purchased Items</th>
                        <th class="py-3 px-4">Total Amount</th>
                        <th class="py-3 px-4">Payment Method</th>
                        <th class="py-3 px-4">Order Status</th>
                        <th class="py-3 px-4">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/40">
                    @forelse($orders as $order)
                        <tr class="hover:bg-muted/30 transition">
                            <td class="py-3 px-4 font-mono font-bold text-foreground">
                                {{ $order->order_number }}
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-foreground">{{ $order->customer_name }}</div>
                                <div class="text-xs text-muted-foreground">{{ $order->customer_email }}</div>
                            </td>
                            <td class="py-3 px-4 text-xs">
                                <ul class="space-y-1">
                                    @foreach($order->items as $item)
                                        <li>
                                            <span class="font-bold text-foreground">{{ $item->quantity }}x</span>
                                            <span>{{ $item->product?->name ?? 'Product #'.$item->store_product_id }}</span>
                                            <span class="text-muted-foreground">(GHS {{ number_format($item->unit_price_cents / 100, 2) }})</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </td>
                            <td class="py-3 px-4 font-bold text-foreground">
                                GHS {{ number_format($order->total_amount_cents / 100, 2) }}
                            </td>
                            <td class="py-3 px-4 text-xs font-mono uppercase">
                                {{ str_replace('_', ' ', $order->payment_method) }}
                            </td>
                            <td class="py-3 px-4">
                                <span class="kt-badge kt-badge-sm kt-badge-success">
                                    {{ $order->status }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-xs text-muted-foreground">
                                {{ $order->created_at?->format('d/m/Y H:i') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-muted-foreground">
                                No store orders recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $orders->links() }}
        </div>
    </div>
</div>
@endsection

