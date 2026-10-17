@extends('layouts.metronic.app')

@section('title', 'Stores & Quota Hub')

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="Institutional Store & Add-On Credits"
        description="Two-tier marketplace: Purchase platform quota packages (SMS, Email, AI tokens) and manage internal/client merchandise."
    >
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <a class="kt-btn kt-btn-outline" href="{{ route('store.products') }}">
                    Manage Products
                </a>
                <a class="kt-btn kt-btn-primary" href="{{ route('store.platform') }}">
                    ⚡ Platform Credit Store
                </a>
            </div>
        </x-slot:actions>
    </x-metronic.page-header>

    {{-- Tier 1: Platform Credit Wallets --}}
    <div class="kt-card p-6 border-l-4 border-l-primary">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-base font-bold text-foreground">Tier 1: Institutional Service Credit Wallets</h3>
                <p class="text-xs text-muted-foreground">Credits are consumed strictly upon delivery verification.</p>
            </div>
            <a href="{{ route('store.platform') }}" class="kt-btn kt-btn-xs kt-btn-primary">+ Top-Up Credits</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- SMS Wallet --}}
            <div class="p-4 bg-muted/30 border border-border rounded-xl">
                <div class="flex items-center justify-between text-xs text-muted-foreground mb-1">
                    <span class="font-bold uppercase tracking-wider">SMS Quota</span>
                    <span>📱</span>
                </div>
                <div class="text-3xl font-extrabold text-foreground">
                    {{ number_format($wallets['sms']->balance ?? 0) }}
                </div>
                <div class="text-[11px] text-muted-foreground mt-2">
                    Total Purchased: {{ number_format($wallets['sms']->total_purchased ?? 0) }} | Used: {{ number_format($wallets['sms']->total_consumed ?? 0) }}
                </div>
            </div>

            {{-- Email Wallet --}}
            <div class="p-4 bg-muted/30 border border-border rounded-xl">
                <div class="flex items-center justify-between text-xs text-muted-foreground mb-1">
                    <span class="font-bold uppercase tracking-wider">Email Quota</span>
                    <span>✉️</span>
                </div>
                <div class="text-3xl font-extrabold text-foreground">
                    {{ number_format($wallets['email']->balance ?? 0) }}
                </div>
                <div class="text-[11px] text-muted-foreground mt-2">
                    Total Purchased: {{ number_format($wallets['email']->total_purchased ?? 0) }} | Used: {{ number_format($wallets['email']->total_consumed ?? 0) }}
                </div>
            </div>

            {{-- AI Token Wallet --}}
            <div class="p-4 bg-muted/30 border border-border rounded-xl">
                <div class="flex items-center justify-between text-xs text-muted-foreground mb-1">
                    <span class="font-bold uppercase tracking-wider">AI Generation Tokens</span>
                    <span>✨</span>
                </div>
                <div class="text-3xl font-extrabold text-foreground">
                    {{ number_format($wallets['ai_token']->balance ?? 0) }}
                </div>
                <div class="text-[11px] text-muted-foreground mt-2">
                    Total Purchased: {{ number_format($wallets['ai_token']->total_purchased ?? 0) }} | Used: {{ number_format($wallets['ai_token']->total_consumed ?? 0) }}
                </div>
            </div>
        </div>
    </div>

    {{-- Tier 2: Tenant Merchandise & Orders Overview --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="kt-card p-6">
            <div class="text-xs font-bold uppercase text-muted-foreground">Catalog Products</div>
            <div class="text-3xl font-extrabold text-foreground mt-2">{{ number_format($productsCount) }}</div>
            <a href="{{ route('store.products') }}" class="inline-block mt-4 text-xs font-semibold text-primary hover:underline">
                View Catalog &rarr;
            </a>
        </div>
        <div class="kt-card p-6">
            <div class="text-xs font-bold uppercase text-muted-foreground">Orders Processed</div>
            <div class="text-3xl font-extrabold text-foreground mt-2">{{ number_format($ordersCount) }}</div>
            <a href="{{ route('store.orders') }}" class="inline-block mt-4 text-xs font-semibold text-primary hover:underline">
                Order History &rarr;
            </a>
        </div>
        <div class="kt-card p-6">
            <div class="text-xs font-bold uppercase text-muted-foreground">Store Revenue</div>
            <div class="text-3xl font-extrabold text-success mt-2">GHS {{ number_format($totalRevenueCents / 100, 2) }}</div>
            <div class="text-xs text-muted-foreground mt-4">Settled directly to tenant account</div>
        </div>
    </div>

    {{-- Recent Orders Table --}}
    <div class="kt-card p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-bold text-foreground">Recent Store Orders</h3>
            <a href="{{ route('store.orders') }}" class="kt-btn kt-btn-xs kt-btn-outline">View All</a>
        </div>

        <div class="overflow-x-auto">
            <table class="kt-table table-auto kt-table-border w-full text-sm">
                <thead>
                    <tr class="border-b border-border/60 text-left text-xs font-semibold text-muted-foreground">
                        <th class="py-3 px-4">Order #</th>
                        <th class="py-3 px-4">Customer</th>
                        <th class="py-3 px-4">Items</th>
                        <th class="py-3 px-4">Total</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/40">
                    @forelse($recentOrders as $order)
                        <tr>
                            <td class="py-3 px-4 font-mono font-bold text-foreground">{{ $order->order_number }}</td>
                            <td class="py-3 px-4">
                                <div class="font-semibold text-foreground">{{ $order->customer_name }}</div>
                                <div class="text-xs text-muted-foreground">{{ $order->customer_email }}</div>
                            </td>
                            <td class="py-3 px-4 text-xs">
                                {{ $order->items->count() }} item(s)
                            </td>
                            <td class="py-3 px-4 font-bold text-foreground">
                                GHS {{ number_format($order->total_amount_cents / 100, 2) }}
                            </td>
                            <td class="py-3 px-4">
                                <span class="kt-badge kt-badge-sm kt-badge-success">{{ $order->status }}</span>
                            </td>
                            <td class="py-3 px-4 text-xs text-muted-foreground">
                                {{ $order->created_at?->format('d/m/Y') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-muted-foreground">
                                No store orders placed yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

